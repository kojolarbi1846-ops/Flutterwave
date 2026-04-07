# Setup Instructions for Flutterwave

## Database Configuration
1. Ensure you have a compatible database management system installed (e.g., MySQL, PostgreSQL).
2. Create a new database named `flutterwave_db`.
3. Run the following SQL command to create necessary tables:
    ```sql
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL
    );
    ```
4. Configure your `.env` file to include the database connection details:
    ```env
    DB_HOST=localhost
    DB_PORT=3306
    DB_DATABASE=flutterwave_db
    DB_USERNAME=your_username
    DB_PASSWORD=your_password
    ```

## API Key Setup
1. Sign up at [Flutterwave](https://www.flutterwave.com).
2. Navigate to the API settings section in your account dashboard.
3. Generate a new API key and note it down.
4. Add this key to your `.env` file as follows:
    ```env
    FLUTTERWAVE_API_KEY=your_api_key_here
    ```

## File Placement Guide
1. Clone the repository using:
    ```bash
    git clone https://github.com/kojiarbi1846-ops/Flutterwave.git
    ```
2. Navigate into the project directory:
    ```bash
    cd Flutterwave
    ```
3. Place your configuration files in the project root, and ensure all dependencies are installed by running:
    ```bash
    npm install      # for node projects
    composer install  # for PHP projects
    ```
4. Start your application based on its type (e.g., using `npm start` for Node.js).

## Conclusion
Follow these instructions carefully to set up your Flutterwave application. For further support, refer to the [Flutterwave documentation](https://developer.flutterwave.com).