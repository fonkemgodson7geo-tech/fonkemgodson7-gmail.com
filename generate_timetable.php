<?php
/**
 * Command-line script to generate new timetable
 */

require_once 'config/config.php';

// Include the timetable functions
require_once 'new_timetable_system.php';

// Generate timetable for May 2026
echo "Starting timetable generation...\n";
generateTimetable('05', '2026');
echo "Timetable generation completed!\n";
?>