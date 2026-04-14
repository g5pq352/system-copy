<?php require_once('../Connections/connect2data.php'); ?>


<?php

print_r($_POST['ids']);

foreach ($_POST['ids'] as $i => $id) {

	$updateSQL = "UPDATE file_set SET file_sort=:file_sort WHERE file_id=:file_id";

	$newsort = $i + 1;

	$sth = $conn->prepare($updateSQL);
	$sth->bindParam(':file_sort', $newsort, PDO::PARAM_INT);
	$sth->bindParam(':file_id', $id, PDO::PARAM_INT);
	$sth->execute();

}
