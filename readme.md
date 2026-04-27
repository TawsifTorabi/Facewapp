# FACEWAPP

### 🚀 Overview

This repository contains a full-stack, containerized application designed to automate meme creation using AI. By utilizing a **producer-consumer architecture**, the system ensures that heavy AI inference tasks do not block the user interface, providing a smooth, responsive experience.

### 🛠️ Tech Stack

-   **Frontend:** PHP (Web Interface for user interaction and state visualization)
    
-   **State Management:** Relational Database (Tracking meme status: `pending`, `processing`, `completed`)
    
-   **Task Queue:** Backend PHP Worker script (Continuous polling/processing of generation tasks)
    
-   **Intelligence:** Integrated AI Model for automated content and layout generation
    
-   **Infrastructure:** Fully Dockerized for seamless environment parity and deployment
    

### 🏗️ Architecture

The tool operates through three primary layers:

1.  **The Producer:** The PHP frontend accepts user inputs and records a "Job" in the database.
    
2.  **The Queue:** The database acts as a persistent state record, managing the lifecycle of each request.
    
3.  **The Consumer:** A background PHP worker monitors the queue, triggers the AI model, and updates the final record upon completion.
    

### ⚙️ Quick Start

Bash

```
# Clone the repository
git clone https://github.com/TawsifTorabi/facewapp.git

# Spin up the containers
docker-compose up -d

# The frontend will be available at http://localhost:8081

```

