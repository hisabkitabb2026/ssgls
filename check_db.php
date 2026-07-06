<?php

$db = new PDO('sqlite:storage/app/database.sqlite');
$r = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
while ($row = $r->fetch()) {
    echo $row[0].PHP_EOL;
}
