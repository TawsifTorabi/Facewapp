
# AI Meme Tool (FaceWapp v2)

A scalable, distributed AI-powered meme generation platform. This tool uses a producer-consumer architecture to handle heavy AI image processing asynchronously, ensuring the web interface remains fast and responsive. In future I'll try to distribute the workers in different kubernetes nodes to distribute server load for faster processing. This tool was developped to be a experimental part as a module of my existing project "PHP-Gallery-Manager" It talks to the Authentication API of `PHP-Gallery-Manager` to authenticate users in and track records on it's own database. 

I've run this application on docker on my homelab server (i3-8130U with 4GB RAM, Intel iGPU) and it was running fine.

## 🏗️ Architecture

The system consists of three main components:

1.  **Web Frontend:** A PHP-based interface for user interaction.
    
2.  **Background Worker:** A PHP worker script that processes the generation queue.
    
3.  **AI Model:** A specialized container running the `face-swap` inference model.
    

----------

## ⚙️ Configuration

The project uses a `config.php` file located at the root. It is configured to work out-of-the-box with the Docker environment.

PHP

```
<?php
define("DB_HOST", "mysql-server"); // Matches the database service name/alias
define("DB_USER", "root");
define("DB_PASS", "root");
define("DB_NAME", "face_swap_app");

// Resolved relative to the container's internal path
define("UPLOAD_PATH", __DIR__ . "/storage/uploads/");

```

----------

## 🐳 Docker Deployment

### 1. Network Setup

For the application to communicate with your database, both must reside on the same Docker network. Create the network manually if it doesn't exist:

Bash

```
docker network create db-network

```

### 2. Custom Storage (Optional)

By default, data is stored in the project folder. To point the application to a specific directory or a high-capacity drive, create a `.env` file:

Bash

```
STORAGE_PATH=/mnt/your-drive/facewapp-data

```

### 3. Docker Compose

Use the following `docker-compose.yml` to spin up the stack. This configuration ensures that both the Web and Worker containers share the same volume and network.

YAML

```
version: "3.8"

services:
  facewapp-web:
    container_name: facewapp-web-v2
    build: .
    ports:
      - "8081:80"
    volumes:
      - ${STORAGE_PATH:-./}:/var/www/html
    networks:
      - db-network
    restart: unless-stopped
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 1G

  facewapp-worker:
    container_name: facewapp-worker-v2
    build: .
    command: php /var/www/html/worker.php
    volumes:
      - ${STORAGE_PATH:-./}:/var/www/html
    networks:
      - db-network
    restart: always
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G

  face-swap-model:
    image: r8.im/codeplugtech/face-swap@sha256:278a81e7ebb22db98bcba54de985d22cc1abeead2754eb1f2af717247be69b34
    container_name: face-swap-model-v2
    networks:
      - db-network
    restart: unless-stopped

networks:
  db-network:
    external: true

```

----------

## ⚠️ Critical Notes

-   **Networking:** If your MySQL server is running in another Docker container, ensure it is also attached to `db-network` and its name matches the `DB_HOST` in your `config.php`.
    
-   **Volume Mounts:** The `${STORAGE_PATH:-./}` syntax ensures that if no path is defined in `.env`, the local directory is used. This allows for "Global" compatibility across different operating systems.
    
-   **Permissions:** Ensure the `storage/uploads/` directory is writable by the web server user inside the container (usually `www-data`).