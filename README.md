# Sprawozdanie - Laboratorium 13 & 13D & 14 (LEMP & Secrets & Merge)

**Autor:** Roman Rybak

## Cel zadania
Zbudowanie i uruchomienie wielokontenerowej aplikacji opartej na stosie **LEMP** (Linux, Nginx, MySQL, PHP) z wykorzystaniem narzędzia **Docker Compose V2**. Dodatkowym elementem środowiska jest usługa phpMyAdmin.

W ramach zadań dodatkowych (Lab 13D i 14) wprowadzono dobre praktyki CI/CD oraz bezpieczeństwa:
* **Lab 13D (Secrets):** Zabezpieczenie danych wrażliwych (haseł do bazy danych) poprzez wykorzystanie mechanizmu *Docker Secrets*, eliminując przekazywanie haseł jawnym tekstem.
* **Lab 14 (Merge):** Podział monolitycznego pliku konfiguracyjnego na mniejsze moduły (plik bazowy i plik nadpisujący) przy pomocy mechanizmu *Merge*.

Zgodnie z wymaganiami:
* Wykorzystano sprecyzowane tagi obrazów.
* Serwery PHP i MySQL zostały odizolowane w bezpiecznej sieci wewnętrznej `backend`.
* Serwer Nginx podłączono do sieci `backend` (do komunikacji z PHP) oraz `frontend` (do ekspozycji na zewnątrz na porcie `4001`).
* Serwer phpMyAdmin udostępniono na porcie `6001`.

---

## 1. Pliki konfiguracyjne
<img width="1056" height="1136" alt="image" src="https://github.com/user-attachments/assets/d63f4ab5-95b0-4b43-94d5-6d737a8c8e45" />

Aby zrealizować zasady CI/CD, główna konfiguracja została podzielona na dwa pliki:

### A. Plik bazowy (`docker-compose.base.yml`)
<img width="1069" height="1123" alt="image" src="https://github.com/user-attachments/assets/3c43b4c6-d855-4d89-af2d-cbb0bee3752e" />

Zawiera wyłącznie niezmienne elementy infrastruktury (obrazy, sieci, wolumeny, zależności).
```yaml
services:
  nginx:
    image: nginx:alpine
    container_name: lemp_nginx
    volumes:
      - ./html:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    networks:
      - frontend
      - backend
    depends_on:
      - php

  php:
    image: php:8.2-fpm-alpine
    container_name: lemp_php
    volumes:
      - ./html:/var/www/html
    networks:
      - backend

  mysql:
    image: mysql:8.0
    container_name: lemp_mysql
    networks:
      - backend

  phpmyadmin:
    image: phpmyadmin/phpmyadmin:5.2
    container_name: lemp_phpmyadmin
    networks:
      - backend

networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge
```

### B. Plik środowiskowy / nadpisujący (`docker-compose.ci.yml`)
<img width="1065" height="751" alt="image" src="https://github.com/user-attachments/assets/59b5d705-c212-44e4-a041-d410b992917a" />
Zawiera konfigurację specyficzną dla danego wdrożenia (mapowanie portów oraz wstrzykiwanie bezpiecznych plików Secrets zamiast haseł w czystym tekście).

```
services:
  nginx:
    ports:
      - "4001:80"

  mysql:
    environment:
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/db_root_password
      MYSQL_DATABASE: testdb
      MYSQL_USER: testuser
      MYSQL_PASSWORD_FILE: /run/secrets/db_password
    secrets:
      - db_root_password
      - db_password

  phpmyadmin:
    ports:
      - "6001:80"
    environment:
      PMA_HOST: mysql
      PMA_USER: root
      PMA_PASSWORD_FILE: /run/secrets/db_root_password
    secrets:
      - db_root_password

secrets:
  db_root_password:
    file: ./db_root_password.txt
  db_password:
    file: ./db_password.txt
```
### C. nginx.conf
<img width="1080" height="363" alt="image" src="https://github.com/user-attachments/assets/c226e4bd-bf75-41ad-9f77-2632d8100dbb" />
Aby Nginx poprawnie obsługiwał pliki PHP, dodano konfigurację przekierowującą zapytania *.php do kontenera php na port 9000.
```
server {
    listen 80;
    server_name localhost;
    root /var/www/html;
    index index.php index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```
### D. html/index.php
Dodatkowy skrypt służący do weryfikacji konfiguracji PHP oraz potwierdzenia komunikacji między kontenerami w sieci backend (pomiędzy usługą PHP a usługą MySQL).
```

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

// Próba nawiązania połączenia TCP z bazą danych
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
echo "</div><hr>";
phpinfo();
?>

```

## 2. Uruchomienie środowiska za pomocą Merge
<img width="777" height="146" alt="image" src="https://github.com/user-attachments/assets/707d608a-34a9-4011-8d86-459c7fb21e3a" />

Środowisko zostało uruchomione w trybie detach z jawnym wskazaniem obu plików konfiguracyjnych. Docker Compose automatycznie scalił je w jedną definicję.
```
docker compose -f docker-compose.base.yml -f docker-compose.ci.yml up -d
```
Dowód poprawnego połączenia plików:
<img width="960" height="54" alt="image" src="https://github.com/user-attachments/assets/8d660f78-d154-4224-af3b-6ffdf806c79a" />
Weryfikacja za pomocą polecenia docker compose ls potwierdza, że do uruchomienia projektu użyto obu plików:
```
romar@DESKTOP-A0R42NS:~/lab13_lemp$ docker compose ls
NAME                STATUS              CONFIG FILES
lab13_lemp          running(4)          /home/romar/lab13_lemp/docker-compose.base.yml,/home/romar/lab13_lemp/docker-compose.ci.yml
```
## 3. Weryfikacja mechanizmu Secrets
Zamiast przekazywać hasła w zmiennych w pliku yaml, zostały one wstrzyknięte jako pliki montowane w ścieżce /run/secrets/. Poniżej znajduje się dowód odczytania sekretu bezpośrednio z wnętrza działającego kontenera bazy danych:
<img width="672" height="37" alt="image" src="https://github.com/user-attachments/assets/c02a48f3-6059-43a1-9168-9cacddcf6c20" />
```
docker exec lemp_mysql cat /run/secrets/db_root_password
```

## 4. Weryfikacja działania i testy
### A. Weryfikacja serwera WWW, PHP oraz połączeń wewnątrz sieci (Port 4001)
<img width="1715" height="682" alt="image" src="https://github.com/user-attachments/assets/83f6f5c3-7906-4ef7-b121-0197ecfe8744" />
### B. Weryfikacja interfejsu bazy danych (Port 6001)
<img width="1710" height="650" alt="image" src="https://github.com/user-attachments/assets/01c28933-9c48-41a1-b001-41a5333abccf" />

## 5. Architektura sieci - Uzasadnienie
Kontener phpMyAdmin został celowo podłączony wyłącznie do sieci backend.
Wynika to z jego roli w systemie: głównym zadaniem phpMyAdmin jest bezpośrednia komunikacja z serwerem baz danych (mysql). Serwer MySQL, ze względów bezpieczeństwa, jest całkowicie odizolowany w chronionej sieci backend, do której publiczny punkt wejścia (Nginx w sieci frontend) nie wpuszcza bezpośredniego ruchu klienckiego.

Dostęp administratora z przeglądarki hosta do interfejsu phpMyAdmin realizowany jest poprzez mechanizm mapowania portów (ports: - "6001:80"). Takie podejście minimalizuje potencjalną powierzchnię ataku.

## Struktura projektu i pliki konfiguracyjne
Poniżej przedstawiono kompletną strukturę katalogów projektu w środowisku VS Code, zawierającą pliki bazowe, pliki środowiskowe (CI), pliki z sekretami oraz konfigurację Nginx.
<img width="1257" height="1060" alt="image" src="https://github.com/user-attachments/assets/0d9f77c8-8f0f-42cf-a1c8-59e26d26b843" />
