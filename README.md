# PHP Project Tracker

A simple personal project tracker built with PHP, MySQL, HTML and CSS. Deliberately constrained to the programming topics in Chapters 23–46 of *The Missing Link: An Introduction to Web Development and Programming* (with basic HTML and CSS for presentation). No React, TypeScript, Node, Composer or frontend build system.

## Run locally with XAMPP

1. Start Apache and MySQL in XAMPP.
2. Clone this repo into `C:\xampp\htdocs\php-project-tracker`.
3. In `http://localhost/phpmyadmin`, open the SQL tab and run `database.sql`.
4. Check `config.php` for your local MySQL connection settings.
5. Visit `http://localhost/php-project-tracker/`.

## Features

- Add, edit and delete project records.
- Required title, optional repository, optional public URL, short description and next step.
- Start date defaults to today but is editable.
- Creation and last-update timestamps maintained in the database.
- Search name, repository, public URL and description.
- Filter by link/repository/next-step availability; sort by title, repository and dates.
- Click GitHub repo and public website links.

## Security

**Run locally only.** No authentication or multi-user permission system is included. Do not expose this app or XAMPP to the public internet. Database actions use prepared queries, output is HTML-escaped and write forms use a session token. These safeguards do not make the app public-production-ready. The app does not automatically sync GitHub metadata; 'last updated' refers to when the project record was last saved.

## Coursework connections

Chapters 23–28: PHP, forms, session data and input handling. Chapters 31–32: conditionals and functions. Chapters 37–42: MySQL schema, CRUD, WHERE, LIKE and ORDER BY. Chapters 43–44: basic security and PHP/database integration.

## Discussion summary

I built a project tracker using a MySQL table for project names, repository links, descriptions, next steps and dates. A traditional HTML form sends POST data to PHP, which validates and stores entries. Another form supplies GET filters; PHP queries the database and renders matching rows as an HTML table. Start dates are editable and update timestamps are automatic.
