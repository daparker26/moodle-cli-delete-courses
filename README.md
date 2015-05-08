# About
This is a simple script for deleting single and mass Moodle courses through the CLI. You can delete courses by course id, category id, or a
user id where the user is enrolled as an 'editingteacher' role.

Add this script to the admin/cli folder in your Moodle root directory.

# Use

Delete single or multiple courses.
		
This script is helpful if you need to delete mass courses
by category or by enrolled faculty, or if you want a 
convenient way to delete a course by ID.

If by category, you must enter the category id. The script
will not delete courses in subcategories.

If by editingteacher, you must enter the user ID. This script will search
for courses in which the user is enrolled as editingteacher. If there are 
multiple editingteacher users in a course, the course will not be deleted.

Options:<br>
-h, --help | Print out this help<br>
-c, --category | Deletes courses by category<br>
-t, --teacher | Deletes courses by teacher<br>
-id, --courseid | Deletes course by id<br>
-f, --force | Force option. Won't prompt for individual courses when deleting by category or teacher.<br>

Example:<br>
$sudo -u www-data /usr/bin/php admin/cli/delete_courses.php
