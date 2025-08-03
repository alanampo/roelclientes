<?php

include "./class_lib/sesionSecurity.php";

if (isset($_GET['requested'])) {
    // return requested value
    if (isset($_SESSION[$_GET['requested']]))
        print $_SESSION[$_GET['requested']];
    else print "null";
} else {
    // nothing requested, so return all values
    print "null";
}

?>
