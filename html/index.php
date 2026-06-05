<?php
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px; background-color: #f9f9f9;'>";
echo "<h1 style='color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>Laboratorium 13 - LEMP Stack</h1>";
echo "<h2>Autor: Roman Rybak</h2>";

echo "<h3>Status komunikacji wewnątrz sieci Docker (Backend)</h3>";
echo "<p>Ten skrypt działa w kontenerze <b>PHP</b> i próbuje połączyć się z kontenerem <b>MySQL</b> używając wewnetrznej sieci Docker.</p>";

$host = 'mysql';
$port = 3306;
$timeout = 2;

echo "<ul>";
echo "<li><b>Adres docelowy:</b> $host</li>";
echo "<li><b>Port docelowy:</b> $port</li>";
echo "</ul>";

$fp = @fsockopen($host, $port, $errCode, $errStr, $timeout);

if ($fp) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "<b>[SUKCES]</b> Połączenie udane! Kontener <i>PHP</i> bez problemu widzi kontener <i>MySQL</i> w izolowanej sieci <code>backend</code>.";
    echo "</div>";
    fclose($fp);
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "<b>[BŁĄD]</b> Brak połączenia: $errStr ($errCode). Kontenery się nie widzą.";
    echo "</div>";
}

echo "</div>";

echo "<hr>";
phpinfo();
?>