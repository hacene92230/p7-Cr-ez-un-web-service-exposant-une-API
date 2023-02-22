# p7-Cr-ez-un-web-service-exposant-une-API
Nothing could be easier to install this project, first please using the "git clone" command clone the following project:
https://github.com/hacene92230/p7-Cr-ez-un-web-service-exponent-une-API.git
once the clone project, copy it into the folder where your php environment runs your different projects, then go inside a new clone project, and do a little "composer install", "composer update", that's it install all the dependencies necessary for the proper functioning of the project and it will update them.
Once the project is installed, please go to the root of the project in the ".env" file and modify the data according to your database server.
Once done, go to the project in the "bin" folder and do: "php console doctrine:database:create", now the database is created then do:
"php console doctrine:schema:update --force", it will force the database to update with all the necessary tables.
Now the project should be functional. Go to localhost/theproject/test by replacing "theproject" with the name of the folder where the newly installed project is located. In test you can test all the functions of the api very simply thanks to the js client that I created especially for the api.