# Sprawozdanie - Laboratorium 13 (Zadanie Obowiązkowe)

**Autor:** Roman Rybak

## Cel zadania
Zbudowanie i uruchomienie wielokontenerowej aplikacji opartej na stosie **LEMP** (Linux, Nginx, MySQL, PHP) z wykorzystaniem narzędzia **Docker Compose V2**. Dodatkowym elementem środowiska jest usługa phpMyAdmin.

Zgodnie z wymaganiami:
* Wykorzystano sprecyzowane tagi obrazów.
* Serwery PHP i MySQL zostały odizolowane w bezpiecznej sieci wewnętrznej `backend`.
* Serwer Nginx podłączono do sieci `backend` (do komunikacji z PHP) oraz `frontend` (do ekspozycji na zewnątrz na porcie `4001`).
* Serwer phpMyAdmin udostępniono na porcie `6001`.

---

## 1. Pliki konfiguracyjne
<img width="1056" height="1136" alt="image" src="https://github.com/user-attachments/assets/d63f4ab5-95b0-4b43-94d5-6d737a8c8e45" />

### docker-compose.yaml
Główny plik orkiestracji definiujący 4 mikrousługi, ich sieci, porty oraz wolumeny.
```yaml
services:
  nginx:
    image: nginx:alpine
    container_name: lemp_nginx
    ports:
      - "4001:80"
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
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: testdb
      MYSQL_USER: testuser
      MYSQL_PASSWORD: testpassword
    networks:
      - backend

  phpmyadmin:
    image: phpmyadmin/phpmyadmin:5.2
    container_name: lemp_phpmyadmin
    ports:
      - "6001:80"
    environment:
      PMA_HOST: mysql
      PMA_USER: root
      PMA_PASSWORD: rootpassword
    networks:
      - backend

networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge
```
### nginx.conf
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
### html/index.php
Dodatkowy skrypt służący do weryfikacji konfiguracji PHP oraz potwierdzenia komunikacji między kontenerami w sieci backend (pomiędzy usługą PHP a usługą MySQL).

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

## 2. Uruchomienie środowiska

Aplikacja została uruchomiona w trybie detach) Wszystkie kontenery zostały pomyślnie zbudowane i uruchomione, a dedykowane sieci poprawnie utworzone.

```
romar@DESKTOP-A0R42NS:~/lab13_lemp$ docker compose up -d
[+] up 45/45
 ✔ Image php:8.2-fpm-alpine        Pulled                                                                                29.8s
 ✔ Image phpmyadmin/phpmyadmin:5.2 Pulled                                                                                 1.4s
 ✔ Image mysql:8.0                 Pulled                                                                                83.1s
 ✔ Image nginx:alpine              Pulled                                                                                40.4s
 ✔ Network lab13_lemp_frontend     Created                                                                                0.0s
 ✔ Network lab13_lemp_backend      Created                                                                                0.0s
 ✔ Container lemp_phpmyadmin       Started                                                                                1.1s
 ✔ Container lemp_php              Started                                                                                1.1s
 ✔ Container lemp_mysql            Started                                                                                1.1s
 ✔ Container lemp_nginx            Started                                                                                0.7s
```

## 3. Weryfikacja działania i testy

### A. Weryfikacja serwera WWW, PHP oraz połączeń wewnątrz sieci (Port 4001)
<img width="1710" height="678" alt="image" src="https://github.com/user-attachments/assets/8c6a10b4-bac1-4d04-8264-9651ef4d0387" />
Po wejściu na adres http://localhost:4001 Nginx poprawnie serwuje plik index.php. Wyświetla się panel z potwierdzeniem nawiązania połączenia TCP między kontenerami php i mysql w sieci backend oraz tabela phpinfo().

### B. Weryfikacja interfejsu bazy danych (Port 6001)
<img width="1720" height="680" alt="image" src="https://github.com/user-attachments/assets/364f3861-a209-4724-9b5b-b4b7b16cd16a" />
Interfejs phpMyAdmin jest dostępny pod adresem http://localhost:6001. Logowanie na użytkownika root przebiegło pomyślnie. Założono nową testową bazę danych o nazwie test_lab13, co potwierdza, że phpMyAdmin posiada poprawne połączenie z kontenerem mysql w sieci backend.

## 4. Architektura sieci - Uzasadnienie

Kontener phpMyAdmin został celowo podłączony wyłącznie do sieci backend.
Wynika to z jego roli w systemie: głównym zadaniem phpMyAdmin jest bezpośrednia komunikacja z serwerem baz danych (mysql). Serwer MySQL, ze względów bezpieczeństwa, jest całkowicie odizolowany w chronionej sieci backend, do której publiczny punkt wejścia (Nginx w sieci frontend) nie wpuszcza bezpośredniego ruchu klienckiego.

Dostęp administratora z przeglądarki hosta do interfejsu phpMyAdmin realizowany jest poprzez mechanizm mapowania portów (ports: - "6001:80"). Takie podejście minimalizuje potencjalną powierzchnię ataku.
