<?php
$con=mysqli_connect("localhost","moneyin8_admin","moneyinmotion!2#","moneyin8_moneyinmotion");
// Check connection
if (mysqli_connect_errno()) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

mysqli_query($con,"UPDATE users SET isCommenrRead=0");
mysqli_query($con,"UPDATE users SET loogin=0");

mysqli_close($con);
?> 
