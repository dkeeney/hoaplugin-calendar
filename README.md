# FSBHOA Calendar Plugin

## Overview
The FSBHOA Calendar is a custom WordPress plugin designed to provide a live, responsive calendar of events for the Four Seasons at Bakersfield Homeowners Association. 

The application is built to handle complex recurring event logic (including exceptions, rescheduling, and cancellations) while providing a highly polished, responsive user interface that adapts to desktops, mobile devices, and high-resolution print exports.

---
## 
## 📄 Project Metadata
* **Author:** David Keeney
* **Contact:** dkeeney@gmail.com
* **Role:** Chairperson, FSBHOA IT/Website Committee
* **License:** Open Source, licensed under the GPLv3
* **Design Tool:** Gemini 2.5 pro, 3.1 pro, 3.5 flash.


## 🚀 Quick Links
* **End-User Documentation:** See [USER_MANUAL.md](docs/USER_MANUAL.md) for full configuration steps, delegate management, and Canva background setup.
* **Commercial License Server:** [HOAplugin.com](https://hoaplugin.com)

---

## 🛠️ System Requirements
* **WordPress:** 5.8 or higher
* **PHP:**  8.x+ (Requires `ext-json` and `ext-pdo`)
* **Frontend:** Vanilla JavaScript, CSS3, HTML5
* **Playwright:** (Node.js) for UI regression testing.
* **Canva:** used for generating monthly background zip files.
* **Composer and RRule:** for dependency installation

---

## 📦 Directory Structure

A clean, high-level map of the plugin architecture. Standard vendor directories, Node modules, and automated test reports generated during build operations are omitted for clarity.

```text
├── admin/                  # Dashboard settings pages, and Pro licensing
├── assets/                 
│   ├── css/                # Visual styling stylesheets
│   ├── images/             # Embedded background zip packages and icons.
│   └── js/                 # Vanilla JavaScript rendering engines.
├── docs/                   # End-user operations documentation
├── includes/               # 
│   ├── Compiler.php        # Matrix compilation engine
│   ├── Repository.php      # Data mapping layer
│   ├── Defaults.php        # Sample data seeded on first activation
│   └── core-bootstrap.php  # Shared hooks, shortcode, and AJAX handlers (also used by Pro)
├── composer.json           # PHP dependency declarations 
├── deploy.sh               # Shell script for production release builds
├── hoaplugin-calendar.php  # Core master plugin entry point file
└── uninstall.php           # App delete cleanup.

```

## 🚀 Development environment
To setup the development and build environment on a Linux system running WordPress.
### 1. Obtain the repository by cloning from github https://github.com/dkeeney/hoaplugin-calendar.git
### 2. Install dependencies using Composer
 Run the following command in the plugin root to install the `rlanvin/php-rrule` package and generate the `vendor/` folder:
   ```bash
   composer install
   composer dump-autoload
   ```

### 3. Install a symbolic link from WordPress base folder to the repository.
   ```bash
   cd /var/www/html/wp-content/plugins/
   ln -s ~/hoaplugin-calendar/ hoaplugin-calendar
   ```

## 🧪 Testing and Sandbox Bridge
The automated regression battery (Playwright plus `TestRunner.php`) lives in the separate `hoaplugin-calendar-test` plugin. Run `npm install` and `./test.sh` from that repository.
* The sandbox scripts set the `hoa_test_mode` cookie, which routes operations to temporary `test_`-prefixed tables and a separate JSON file to avoid risking live database corruption.

---

## 1.  Terminology
The calendar relies on a parent-child record structure to handle the complexities of recurring events without data duplication. Understanding these definitions is critical for working on the codebase.

* **Master Record:** The original event declaration. This contains global metadata (title, category, location, flyer) and the scheduling rules (either a one-time date or a recurring `RRule`).
* **Instance:** A generated, discrete occurrence of a Master event that renders on a specific day in the calendar grid. Instances are generated dynamically based on the rules of the most recent master or pivot as modified by the hole and move exceptions.
* **Pivot (Pattern change):** A child record that declares a permanent change in the recurring pattern of a event's instances, taking effect at a specific instance. A pivot alters all subsequent instances.
* **Hole (Cancellation):** A child record that explicitly cancels a specific instance of a recurring event, preventing it from rendering in the calendar. A hole record is identified as a record with a master_id, a pivot_id, and a status of "cancelled".
* **Move (Reschedule):** A child record declaring an out-of-sequence instance. It is used to move an event to a new date/time and is always paired with a *Hole* record to suppress the original instance.
* **Exception** A pivot, move, or hole record. These are ordered child records of the master. Each changes the pattern of instances generated.
* **Boomerang:** A user action in the Admin UI where an exception (a Move or a Hole) is reverted to its original state. The system resolves this by deleting the specific Hole and Move records.
* **Triple-Exception:** A complex edge case operation where a single instance of a recurring event experiences a move/time change, and is subsequently cancelled or moved *again*.

---

Third-Party Engine Infrastructure
The scheduling backend architecture integrates the rlanvin/php-rrule library via Composer to guarantee absolute compliance with the iCalendar RFC 5545 international recurrence specification.

Primary Matrix Computation: The dependency is leveraged directly by Compiler.php during the caching pipeline. It parses complex RRule constraints to generate discrete event instances within your timeline window, flattening the output into the ultra-fast public snapshot file: wp-content/uploads/hoaplugin-calendar/calendar-events.json.

Personal Device Syndication: The engine uses this data structure to format and stream standard .ics data payloads via the Webcal loop, enabling residents to subscribe to community sequences directly through native device clients (e.g., Apple Calendar, Google Calendar, Outlook).

---

## E. The Behaviors
When an Admin or Delegate interacts with the calendar, the JavaScript packages the payload with a specific `edit_mode` and sends it to the PHP Controller. The `Repository.php` class then executes the precise database mutations.

After any edit action the compiler is called to regenerate the .json file containing all events to be displayed.  The javascript code then re-renders the calendar using the new .json file.

### 1. Meta-Data Edits (`soft_save`)
* **Trigger:** User changes the Title, Description, Flyer URL, Location, or Category, but leaves the Time and Recurrence Rules alone.
* **Backend Action:** The Repository updates the `fsbhoa_events` row on the master, in-place without triggering the complex exception engine.

### 2. Changing the Rules (`maybe_pivot_series`)
* **Trigger:** User changes the Start Time, End Time, or the `RRule` of an existing recurring event.
* **Backend Action:** The system detects a "DNA Change". 
    * If the edit occurs on the original start date of the rule, it updates the record in-place.
    * If the edit occurs in the middle of a series, it creates a **New Pivot** record starting on that date, and automatically triggers `delete_downstream()` to wipe out any orphaned exceptions tied to the old rule.


### 3. Drag-and-Drop Rescheduling (`instance_move`)
* **Trigger:** User drags an event instance to a new day cell, or uses the "Reschedule" modal.
* **Backend Action (`move_event_instance`):** * The Repository computes the "Natural" time the event was supposed to occur.
    * It inserts a *Hole* (status: `cancelled`) at the natural time.
    * It inserts a *Move* (status: `active`) at the new target time.
    * *Collision Detection:* If the user drags a previously moved event instance, the system seamlessly cleans up the old move records to prevent database bloating.

* **Trigger:** User holds shift and drags an event instance to a new day cell, or uses the "reschedule" modal with "Reschedule all remaining".
* **Backend Action** * The Repository performs a pivot at that point causing all subsequent instances of the event to follow the new pattern. It will remove all downstream exception records.

### 4. Cancellations (`instance_cancel` & `master_cancel`)
* **Trigger:** User drops an event in the top red "Cancel" header zone, or clicks "Cancel ONLY this instance."
* **Backend Action:** Inserts a child record on that specific date with the status set to `cancelled`. The Compiler will skip this date when generating the JSON.

* **Trigger:** User holds shift and drags an event instance to the header zone, or clicks "Terminate event".
* **Backend Action:** Ends the event by changing the RRule on the most recent master or pivot record to include a termination date. No event instances are generated beyond that date.

### 5. The Restorations
* **Trigger:** User clicks "Restore Instance" or "Resume Series."
* **Backend Action (`restore_hole` / `resume_series`):** Deletes the specific `cancelled` row from the database, or strips the `UNTIL` clause from an RRule, instantly reverting the calendar grid back to its natural recurring state.

## F. Pivot Behavior
As stated previously, a pivot is a change in the pattern during a repeating event. Associated with a pivot is a base date/time.  This is usually the date that would have been the last instance of the previous sequence. Anything on or after this date will be in the new pattern.  

A pivot is a child record of the master and has the parent_id of the master.  There can be any number of pivot child records, ordered by its start date, and each represents a change in pattern.

When the pattern on an instance changes, all child records downstream from it are removed. This means that all instances subsequent to this date change to the new pattern.

Note that the start date on the master record is the base date and is not necessarily the date of the first instance. This could happen if an RRule specifies a pattern for which the base date would not be a member.  For example if a Tuesday is selected to create a pattern and the pattern was for every Monday, the first instance would be the following Monday.

The date on a pivot would be the base date of the new sequence which is a date which would have been the last date of the previous sequence.  The first intance of the pivot would be the first intance of the new RRule following that date.

Edge cases:
* **New Master** When a new repeating event is created, it creates a new master record with the RRule and the start date of the day that was clicked.  That start date is used as the reference date to generate the instances that follow.

* **Change RRule on first instance of Master** If the RRule changes on the first instance created by the original RRule on the master is changed, it should not create a new pivot record but rather it should just change the pattern on the master record (and remove all downstream records).

* **Change RRule on first instance following a pivot** If the RRule changes on the first instance created by the original RRule on a pivot is changed, it should not create a new pivot record but rather it should just change the pattern on the pivot record (and remove all downstream records).

* **Change RRule on a subsequent instance**  If an instance after the first instance of a master or pivot is changed, a new pivot record is created. The RRule on the previous master or pivot will be stop being the rule-in-effect. The RRule on the new pivot will then determine the next instance of the event.

* **Bomerang** If the first instance of a series is modified with a new date that matches what would have been the next instance of the previous series (which also is the date of the pivot that is in effect), AND the RRule and time are the same as the previous record, the current pivot record is updated with the new DNA and downstream records are removed.  This is not entirely self healing in that there will be two pivot records with the same DNA but the display and all operations are not affected.

* **Reschedules and Cancellations**  If a single instance of a series is rescheduled, a move record is created as a child record of the master and references the master id and the new date where the instance will be displayed. It also generates a hole record at the location of the original instance to suppress the display of the original instance. The master or pivot is not affected.  If a single instance of a sequence is cancelled, a hole child record is generated with the date of the original instance.

* **Shift Drag of an instance**  If an instance is dragged to a new day, it constitues a RRule change as described above.  The RRule is adjusted based on it's previous content to ajust to the new day.


---
