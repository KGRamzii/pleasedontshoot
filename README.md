# Laravel: Pink Slip

This application is a Laravel-based platform that allows users to challenge each other in a competitive game format. The system tracks challenges, ranks users based on the outcomes, and logs the ranking history of each user. The application also integrates with Discord for notifications.

## Features

- **Challenge Management**: Users can issue, accept, or decline challenges.
- **Rankings**: The ranking system adjusts user ranks based on the outcome of challenges.
- **Rank History Tracking**: Every rank change is logged for future reference.
- **Witness Role**: A witness can submit the outcome of a challenge.
- **Discord Integration**: Notifications are sent to a Discord channel for challenge updates.

## Table of Contents

[Installation](#installation)


## Installation

1. Clone the repository:

2. (Installing Laravel herd come with basically all the things you need to run laravel out of the box)

3. composer install (This installs are the dependencies that are set up in composer.json)

4. npm install
   
5. Set up the .env file: 
cp .env.example .env

6. Generate application key:
php artisan key:generate

7. Set up your database credentials in the .env file, and then run migrations:
php artisan migrate

8. Install Livewire Volt if not already included:
composer require livewire/livewire
npm install livewire-volt

9. Set up Discord webhook URL in the .env file:
DISCORD_WEBHOOK_URL=your-webhook-url-here

10. Run the development server:
php artisan serve (If you have HERD installed, there is no need, all you have to do is point to the directory of your site)
bpm run dev (To run vite for front-end Livewire to work as intended)




