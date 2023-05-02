## Installation of the Application and its packages


V2 for Matching the data in the rules table in the database with the uploaded files with the feature of selecting multiple files and displaying the matching data on the screen<br />


Database file is in home directory 'test_project.sql'<br />

Instale os pacotes com `composer install`<br />
And install them<br />
-sudo apt-get install tesseract-ocr<br />
-brew install pkg-config imagemagick<br />
-sudo pecl install imagick<br />
-composer require barryvdh/laravel-dompdf<br />
-composer require phpoffice/phpword<br />
Copie o arquivo .env.example para .env com `cp .env.example .env`<br />
Gere uma chave unica para a aplicação com `php artisan key:generate`<br />
Add databases php artisan migrate<br />
Inicie a aplicação com `php artisan serve`<br />
Warteschlange laufen lassen `php artisan queue:work --queue=ocr`<br />
