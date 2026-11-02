<?php

echo password_hash("1234", PASSWORD_BCRYPT);
echo "<br>";
echo password_hash("0914648aba3c23d842e51969669c32c1", PASSWORD_DEFAULT);

?>