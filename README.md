# Why WordPress with Composer and the classic directory structure?
* Support for the Apache webserver while a usual "skeleton composer installation" works only with Nginx.
    * Very useful with the multisite setup, because saves classic configs from the Codex for both [nginx](https://wordpress.org/support/article/nginx/) and [Apache](https://wordpress.org/support/article/htaccess/)
* The supported folder structure by the WP Engine, which let you use the composer for the Wordpress and keep your SLA and warranties from the vendor.
* Simplified CI/CD for the basic WordPress installation approach
    * You just build with the composer (plus maybe your frontend), pack the `web` folder, and put its content to the server.
