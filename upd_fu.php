<?php

include('inc/inc.php');

// Start session
session_start();

// Register $errors array - just in case
if (!session_is_registered("errors"))
    session_register("errors");

// Clear all previous errors
$errors=array();

// Register form data in the session
if(!session_is_registered("fu_data"))
    session_register("fu_data");

// Get form data from POST fields
foreach($_POST as $key => $value)
    $fu_data[$key]=$value;

// Check submitted information
if (strtotime($fu_data['date_start'])<0) { $errors['date_start']='Invalid start date!'; }
if (strtotime($fu_data['date_end'])<0) { $errors['date_end']='Invalid end date!'; }

if (count($errors)) {
    header("Location: $HTTP_REFERER");
    exit;
} else {
    $fl="date_start=\"$date_start\",date_end='$date_end',type='$type',description='$description'";
    $fl.=",sumbilled='$sumbilled'";

    if ($id_followup>0) {
		// Prepare query
		$q="UPDATE lcm_followup SET $fl WHERE id_followup=$id_followup";
    } else {
		$q="INSERT INTO lcm_followup SET id_followup=0,id_case=$id_case,$fl";
    }

    // Do the query
    if (!($result = lcm_query($q))) die("$q<br>\nError ".lcm_errno().": ".lcm_error());
    //echo $q;

    // Clear the session
    session_destroy();

    // Send user back to add/edit page's referer
    header('Location: ' . $fu_data['ref_edit_fu']);
}

?>
