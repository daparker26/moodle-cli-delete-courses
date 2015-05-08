<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI script for deleting courses, single and en masse. Use at your own risk.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2015 Daniel Parker (Black River Technical College)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');

error_reporting(E_ERROR | E_WARNING | E_PARSE);

// CLI Options
list($options, $unrecognized) = cli_get_params(array(
	'help'=>false,
	'category'=>'',
	'teacher'=>'',
	'courseid'=>'',
	'force'=>false
	),
    array(
	'h'=>'help',
	'c'=>'category',
	't'=>'teacher',
	'id'=>'courseid',
	'f'=>'force'
	));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "
        Delete single or multiple courses.
		
        This script is helpful if you need to delete mass courses
        by category or by enrolled faculty, or if you want a 
        convenient way to delete a course by ID.

        If by category, you must enter the category id. The script
        will not delete courses in subcategories.

        If by editingteacher, you must enter the user ID. This script will search
        for courses in which the user is enrolled as editingteacher. If there are 
        multiple editingteacher users in a course, the course will not be deleted.

        Options:
        -h, --help            Print out this help
        -c, --category        Deletes courses by category
        -t, --teacher         Deletes courses by teacher
        -id, --courseid       Deletes course by id
        -f, --force           Force option. Won't prompt for individual courses 
                              when deleting by category or teacher.

        Example:
        \$sudo -u www-data /usr/bin/php admin/cli/delete_courses.php
        ";

    echo $help;
    die;
} elseif ($options['courseid']) {
	
	$id = cli_input('Enter the course id');
	$id = clean_param($id, PARAM_INT);
	
	if ($id < 1) cli_error("Invalid course number. Aborting..\n");
	
	try {
		$course = get_course($id);
	} catch (Exception $e) {
		throw cli_error('The course cannot be found. Ensure you are using the correct ID number');
	}
	
	$prompt = 'Delete "' . $course->fullname . ' (' . $course->shortname . ')"? (Y/N)';
	$input = cli_input($prompt);
	$input = clean_param($input, PARAM_ALPHA);
	
	if (strtolower($input) == 'y') {
		try {		
			delete_this_course($course);
			echo 'Course deleted FOREVER!' . "\n";
			exit(0);
		} catch (Exception $e) {
			throw cli_error('Hmmm. Something went wrong.' . "\n");
		}
	} else {
		die();
	}		
} elseif ($options['category']) {
	
	$id = cli_input('Enter the category ID');
	$id = clean_param($id, PARAM_INT);	
	
	if ($id < 1) cli_error("You must specify a valid Category ID. Aborting...\n");
	
	$category = array();
	try
	{
		$category = get_courses($id, "c.sortorder ASC", "c.id, c.fullname, c.shortname");
	}
	catch (Exception $e)
	{
		throw cli_error('The category cannot be found. Ensure you are using the correct ID number.' . "\n");
	}	
	
	// If it returned something
	if (!empty($category)) {
		
		$cat_field = $DB->get_record('course_categories', array('id' => $id));
		$cat_count = count($category);
	
		$prompt = 'Delete all ' . $cat_count . ' courses in ' . $cat_field->name . '? (Y/N)';
		$input = cli_input($prompt);
		$input = clean_param($input, PARAM_ALPHA);
		
		if (strtolower($input) == 'y') {			
			foreach ($category as &$course)
			{				
				if (!$options['force']) {
					$coursePrompt = 'Delete ' . $course->shortname . ' (' . $course->fullname . ')? (Y/N)';
					$courseInput = cli_input($coursePrompt);
					$courseInput = clean_param($courseInput, PARAM_ALPHA);
					
					if (strtolower($courseInput) == 'y') {
						delete_this_course($course);
					} else {
						continue;
					}
				} else {
					delete_this_course($course);
				}				
			}
			$courses_deleted = $cat_count - count(get_courses($id, "c.sortorder ASC", "c.id"));
			echo $courses_deleted . ' courses deleted of ' . $cat_count . "\n";
			exit(0);
		} else {
			echo "Aborting...\n";
			die();
		}
	} else {
		cli_error("No courses found in category. Aborting...\n");
	}	
} elseif ($options['teacher']) {
	$id = cli_input('Enter the user ID');
	$id = clean_param($id, PARAM_INT);	
	
	if ($id < 1) cli_error("You must specify a valid User ID. Aborting...\n");
	
	$sql = 'SELECT c.id, c.fullname, c.shortname
				FROM {course} c
				JOIN {context} ctx ON c.id = ctx.instanceid
				JOIN {role_assignments} ra ON ra.contextid = ctx.id
				WHERE ra.roleid =3 AND
				(SELECT count(*) FROM {role_assignments} ra WHERE ra.roleid = 3 AND ra.contextid = ctx.id) < 2 AND
				ra.userid = ?';
				
		$courses = array();
		try
		{
			$courses = $DB->get_records_sql($sql, array($id));
		} catch (Exception $e) {
			throw cli_error("Something went wrong querying the database. Aborting...\n");
		}
		// If there are courses
		if (!empty($courses)) {
			$user = $DB->get_record('user', array('id'=>$id));
			$course_count = count($courses);
			
			$prompt = "Delete " . $course_count . " courses in which " . $user->firstname . " " . $user->lastname . " (" . $user->email . ") is enrolled as sole faculty? (Y/N)";
			$input = cli_input($prompt);
			$input = clean_param($input, PARAM_ALPHA);
			
			if (strtolower($input) == 'y') {
				foreach ($courses as &$course) {
					if (!$options['force']) {
						$coursePrompt = 'Delete ' . $course->shortname . ' (' . $course->fullname . ')? (Y/N)';
						$courseInput = cli_input($coursePrompt);
						$courseInput = clean_param($courseInput, PARAM_ALPHA);
						
						if (strtolower($courseInput) == 'y') {
							delete_this_course($course);
						} else {
							continue;
						}
					} else {
						delete_this_course($course);
					}
				}
				exit(0);
			}
		} else {
			cli_error('There are no courses in which the user is enrolled as editingteacher');
		}
}

/**
 * Deletes courses in a CLI friendly way
 * @param stdClass course - Moodle course object
 * @return void
 */
function delete_this_course($course) {
	$courseObj =  (!is_object($course)) ? get_course($course) : $courseObj = $course;

	echo 'Deleting ' . $courseObj->shortname . ' (' . $courseObj->fullname . ")\n";
	
	try {
		// Output buffer because I don't want it spitting tons of HTML at me
		ob_start();
		delete_course($courseObj);
		fix_course_sortorder();
		// End output buffer
		ob_end_clean();
	} catch (Exception $e) {
		echo "Error deleting " . $courseObj->shortname . ' (' . $courseObj->fullname . ")\n";
		echo "Exception Message: " . $e . "\n";
	}
}