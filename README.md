# Bionic Reading Application

A web-based application designed to enhance reading speed and comprehension using Bionic Reading techniques. Users can upload documents, read them in a specialized interface that highlights parts of words to guide the eye, and track their reading progress.

## Features

-   **User Account System**: Secure registration and login functionality.
-   **Document Management**: Upload and manage personal documents.
    -   Supports PDF and DOCX formats.
-   **Bionic Reading Mode**: Automatically processes text to highlight initial letters of words, facilitating faster reading.
-   **Progress Tracking**:
    -   Save reading sessions and resume where you left off.
    -   Track words per minute (WPM).
    -   Mark documents as completed.
-   **Admin Dashboard**: Administrative interface for managing users and viewing platform statistics.
-   **Responsive Design**: Modern UI with a glassmorphism aesthetic, optimized for various devices.

## Tech Stack

-   **Backend**: PHP
-   **Frontend**: HTML5, CSS3, JavaScript
-   **Database**: MySQL
-   **Dependencies**:
    -   [`phpoffice/phpword`](https://github.com/PHPOffice/PHPWord) - For handling Word documents.
    -   [`smalot/pdfparser`](https://github.com/smalot/pdfparser) - For parsing PDF files.
    -   [`vanderlee/syllable`](https://github.com/vanderlee/phpSyllable) - For text processing and syllable handling.

## Installation & Setup

### Prerequisites

-   PHP (7.4 or higher recommended)
-   MySQL Server
-   Composer
-   Web Server (Apache/Nginx) - e.g., via XAMPP, WAMP, or MAMP.

### Steps

1.  **Clone the Repository**
    ```bash
    git clone <repository-url>
    cd Final_Individual_Project
    ```

2.  **Install Dependencies**
    Run the following command in the project root to install PHP dependencies:
    ```bash
    composer install
    ```

3.  **Database Configuration**
    -   Create a new MySQL database (e.g., `bionic_reading_db`).
    -   Import the `database.sql` file provided in the root directory into your database.
    -   Open `config.php` and update the database credentials:
        ```php
        define('DB_SERVER', 'localhost');
        define('DB_USERNAME', 'your_username');
        define('DB_PASSWORD', 'your_password');
        define('DB_NAME', 'bionic_reading_db');
        ```

4.  **Run the Application**
    -   Place the project folder in your web server's root directory (e.g., `htdocs` for XAMPP).
    -   Access the application via your browser:
        ```
        http://localhost/Final_Individual_Project
        ```

## Directory Structure

-   `css/` - Stylesheets for the application.
-   `js/` - JavaScript files for frontend logic.
-   `includes/` - Reusable PHP code snippets (header, footer, database connection).
-   `vendor/` - Composer dependencies.
-   `user_uploads/` - Directory where uploaded user documents are stored.
-   `logs/` - Error and activity logs.

## Usage

1.  **Register/Login**: Create an account to start.
2.  **Upload**: Go to the dashboard and upload a PDF or Word document.
3.  **Read**: Click on a document to open the reading view. Use the play/pause controls to track your session.
4.  **Track**: View your reading stats on the dashboard.


URL to the school server: 

http://169.239.251.102:341/~ajak.panchol/uploads/Final_Individual_Project/index.php

## License



This project is for educational purposes.
