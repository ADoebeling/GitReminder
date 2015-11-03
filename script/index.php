<!DOCTYPE HTML>
<html>
<head>
	<meta http-equiv="refresh" content="4200000" />
	<meta name="titel" title="GitReminder Do IT" />
	<meta name="description" content="Free GitReminder for GitHub Pls. check GitHub.com an search GitReminder" />
</head>
<body>

	<?php
		if (!isset($_GET['pwd']))
		{
			die("Da fehlt etwas!");
		}
		error_reporting (1);
		error_reporting(E_ALL);
		include 'gitreminder.php';
	?>

</body>
</html>