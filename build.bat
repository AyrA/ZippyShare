@ECHO OFF
IF EXIST zippy.tar DEL zippy.tar
IF EXIST zippy.host DEL zippy.host
"C:\Program Files\7-Zip\7z.exe" a -- zippy.tar INFO zippy.php
"C:\Program Files\7-Zip\7z.exe" a -- zippy.tar.gz zippy.tar
DEL zippy.tar
REN zippy.tar.gz zippy.host