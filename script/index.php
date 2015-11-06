<?php
error_reporting (1);
error_reporting(E_ALL);
?>
<!DOCTYPE HTML>
<html>
<head>
	<meta http-equiv="refresh" content="4200000" />
	<meta name="titel" title="GitReminder Do IT" />
	<meta name="description" content="Free GitReminder for GitHub Pls. check GitHub.com an search GitReminder" />
	<meta charset="ISO-8859-1" />
	<meta http-equiv="refresh" content="10">
	<style>body,h2,html{font-family:sans-serif;}</style>
</head>
<body>

	<?php
		if (!isset($_GET['pwd']))
		{
			die("Da fehlt etwas!");
		}

		foreach($_GET as $key => $value)
		{
			echo $value."<br>";
		}

		include 'gitreminder.php';

	?>
</body>
</html>