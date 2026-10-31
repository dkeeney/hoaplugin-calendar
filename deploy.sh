#!/bin/bash

# 1. Define Paths and Variables
DIR_NAME=$(basename "$PWD")
TIMESTAMP=$(date +%Y%m%d_%H%M)
ZIP_FOR_SERVER="${DIR_NAME}.zip"
ZIP_FOR_WPORG="${DIR_NAME}-wporg.zip"
TESTBED_WP_DIR="/var/www/html/wp-content/plugins/$DIR_NAME"
MAIN_PHP_FILE="${DIR_NAME}.php"

# ---------------------------------------------------------
# VERSION SYNC LOGIC
# ---------------------------------------------------------
if [ -f "$MAIN_PHP_FILE" ]; then
    # Extract the version number (grabs "1.1.25" from "* Version: 1.1.25")
    PLUGIN_VERSION=$(grep -i "^ \* Version:" "$MAIN_PHP_FILE" | awk -F':' '{print $2}' | xargs)

    if [ -n "$PLUGIN_VERSION" ]; then
        echo "Building Free Release: v$PLUGIN_VERSION"
        echo "Syncing version $PLUGIN_VERSION to readme.txt..."
        # Replace the Stable tag line in readme.txt using GNU sed
        sed -i "s/^Stable tag: .*/Stable tag: $PLUGIN_VERSION/" readme.txt
    else
        echo "Warning: Could not extract version number from $MAIN_PHP_FILE"
    fi
else
    echo "Warning: Main plugin file $MAIN_PHP_FILE not found for version sync."
    exit 1
fi

# Clean up old local zips
rm -f "$ZIP_FOR_SERVER" "$ZIP_FOR_WPORG"

# ---------------------------------------------------------
# STEP 1: PUSH TO LOCAL TESTBED (For Regression Testing)
# ---------------------------------------------------------
echo "Syncing to local Testbed WordPress..."
sudo rsync -av --delete \
    --exclude=".git*" \
    --exclude=".aider*" \
    --exclude="*.zip" \
    --exclude="deploy.sh" \
    --exclude="CLAUDE.md" \
    --exclude="test.sh" \
    --exclude=".DS_Store" \
    ./ "$TESTBED_WP_DIR/" > /dev/null

# ---------------------------------------------------------
# STEP 2: BUILD THE ZIPS
# ---------------------------------------------------------
echo "Blocking out the noise and zipping up $DIR_NAME..."

# Step OUT of the directory so zip grabs the wrapper folder
cd ..

# BUILD A: The Internal/Server Payload
zip -r9 "$DIR_NAME/$ZIP_FOR_SERVER" "$DIR_NAME" \
    -x "$DIR_NAME/*.git*" \
    -x "$DIR_NAME/*.zip" \
    -x "$DIR_NAME/*/bin/*" \
    -x "$DIR_NAME/.aider*" \
    -x "$DIR_NAME/test.sh" \
    -x "$DIR_NAME/README.md" \
    -x "$DIR_NAME/CLAUDE.md" \
    -x "$DIR_NAME/deploy.sh" \
    -x "$DIR_NAME/assets/screenshot-*.png" \
    -x "$DIR_NAME/.DS_Store" > /dev/null

# BUILD B: The WordPress.org Payload (Strictly Sanitized)
zip -r9 "$DIR_NAME/$ZIP_FOR_WPORG" "$DIR_NAME" \
    -x "$DIR_NAME/*.git*" \
    -x "$DIR_NAME/*.zip" \
    -x "$DIR_NAME/*/bin/*" \
    -x "$DIR_NAME/.aider*" \
    -x "$DIR_NAME/docs/*" \
    -x "$DIR_NAME/README.md" \
    -x "$DIR_NAME/CLAUDE.md" \
    -x "$DIR_NAME/test.sh" \
    -x "$DIR_NAME/deploy.sh" \
    -x "$DIR_NAME/assets/screenshot-*.png" \
    -x "$DIR_NAME/.DS_Store" > /dev/null


# Step back IN to the directory
cd "$DIR_NAME" || exit

# list the zip
unzip -l "$ZIP_FOR_SERVER"

# ---------------------------------------------------------
# STEP 3: CONDITIONAL DEPLOYMENT (THE MAGIC ROUTER)
# ---------------------------------------------------------
if [[ "$PLUGIN_VERSION" == *"RC"* ]]; then
    # It is a Release Candidate! Skip the server.
    echo "--------------------------------------"
    echo "🛑 RC TAG DETECTED (v$PLUGIN_VERSION)."
    echo "🛑 SKIPPING UPLOAD TO LICENSE SERVER."
    echo "✅ Local Testbed is updated and ready for regression testing."
    echo "✅ Zips created locally but NOT transmitted."
else
    # It is a production release! Send the server zip to Arvixe.
    echo "Deploying to License Server..."
    scp "$ZIP_FOR_SERVER" scguild@keolight.com:~/hoaplugin.com/wp-content/uploads/edd/
    
    # Clean up the server zip locally since it was transmitted
    rm -f "$ZIP_FOR_SERVER"
    
    echo "--------------------------------------"
    echo "✅ PRODUCTION DEPLOYMENT COMPLETE."
    echo "✅ Uploaded $ZIP_FOR_SERVER to Keolight server."
    echo "✅ $ZIP_FOR_WPORG is ready locally for WP.org submission."
fi
