<?php
return [
 'app'=>['url'=>'http://localhost:18080','timezone'=>'Europe/Lisbon'],
 'database'=>['host'=>'db','port'=>3306,'name'=>'emoto_test','user'=>'emoto_test','password'=>'test-only-password'],
 'smtp'=>['host'=>'smtp.invalid','port'=>465,'encryption'=>'ssl','username'=>'test@example.invalid','password'=>'not-a-real-secret','from_email'=>'test@example.invalid','from_name'=>'E-Moto Test'],
 'backups'=>['retention_count'=>3],
];
