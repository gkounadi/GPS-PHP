<?php
session_start();
$loggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Golf Course GPS & Scoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-100 font-sans" data-loggedin="<?= $loggedIn ? 'true' : 'false' ?>">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Golf Course GPS & Scoring</h1>

        <!-- User Info Dropdown -->
        <div id="userInfo" class="flex justify-end mb-4 <?= !$loggedIn ? 'hidden' : ''; ?>">
          <div class="relative">
            <button id="userDropdownBtn" class="flex items-center bg-white p-3 rounded-full shadow hover:bg-gray-100 focus:outline-none transition">
              <span id="userName" class="mr-2 font-semibold text-gray-700"><?= htmlspecialchars($userName) ?></span>
              <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
              </svg>
            </button>
            <div id="userDropdownMenu" class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded shadow-lg z-dropdown hidden">
              <button id="viewProfile" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">View Profile</button>
              <button id="logoutButton" class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">Logout</button>
            </div>
          </div>
        </div>

        <!-- Auth Section -->
        <div id="authSection" class="bg-white p-4 rounded shadow mb-4 <?= $loggedIn ? 'hidden' : ''; ?>">
            <h2 class="text-xl font-semibold mb-4">Login or Register</h2>
            <form id="loginForm">
                <div class="error" id="loginError" style="display:none"></div>
                <label for="loginEmail" class="block">Email</label>
                <input type="email" id="loginEmail" class="w-full p-2 border rounded" required>
                <label for="loginPassword" class="block">Password</label>
                <input type="password" id="loginPassword" class="w-full p-2 border rounded" required>
                <button type="submit" id="loginButton" class="bg-blue-500 text-white px-4 py-2 rounded">Login</button>
                <p class="mt-2">No account? <a href="#" id="showRegister" class="text-blue-500">Register</a></p>
            </form>
            <form id="registerForm" class="hidden">
                <div class="error" id="registerError" style="display:none"></div>
                <label for="registerName" class="block">Name</label>
                <input type="text" id="registerName" class="w-full p-2 border rounded" required>
                <label for="registerEmail" class="block">Email</label>
                <input type="email" id="registerEmail" class="w-full p-2 border rounded" required>
                <label for="registerPassword" class="block">Password</label>
                <input type="password" id="registerPassword" class="w-full p-2 border rounded" required>
                <button type="submit" id="registerButton" class="bg-green-500 text-white px-4 py-2 rounded">Register</button>
                <p class="mt-2">Already have an account? <a href="#" id="showLogin" class="text-blue-500">Login</a></p>
            </form>
        </div>

        <!-- Profile Section -->
        <div id="profileSection" class="bg-white p-4 rounded shadow mb-4 hidden">
            <h2 class="text-xl font-semibold">Profile</h2>
            <p><strong>Email:</strong> <span id="profileEmail"></span></p>
            <p><strong>Name:</strong> <span id="profileName"></span></p>
            <h3 class="text-lg font-semibold mt-4">Round History</h3>
            <div id="roundHistory"></div>
            <button id="backToGame" class="bg-blue-500 text-white px-4 py-2 rounded mt-4">Back to Game</button>
        </div>

        <!-- Game Section -->
        <button id="startNewRound" class="bg-green-500 text-white px-4 py-2 rounded mb-4 hidden">Start New Round</button>
        <div id="gameSection" class="hidden <?= $loggedIn ? '' : 'hidden'; ?>">
            <div class="bg-white p-4 rounded shadow mb-4">
                <h2 class="text-xl font-semibold">Distance Calculator</h2>
                <div id="map" class="my-4"></div>
                <div class="flex items-center mb-2">
                    <label for="unitToggle" class="mr-2">Meters</label>
                    <input type="checkbox" id="unitToggle" class="toggle">
                    <label for="unitToggle" class="ml-2">Yards</label>
                </div>
                <p id="distanceToCurrentGreen" class="mb-2"></p>
                <p id="distanceToPoint" class="mb-2"></p>
                <p id="distanceToGreen" class="mb-2"></p>
                <p id="courseInfo" class="font-semibold"></p>
            </div>

            <div class="bg-white p-4 rounded shadow mb-4">
                <h2 class="text-xl font-semibold">Hole <span id="holeNumber">1</span></h2>
                <div class="mt-4">
                    <label for="scoreSlider" class="block font-semibold">Hole Score</label>
                    <input type="range" id="scoreSlider" min="1" max="10" value="4" class="w-full">
                    <p id="scoreDisplay" class="text-center mt-2">4</p>
                </div>
                <div class="flex justify-between mt-4">
                    <button id="prevHole" class="bg-blue-500 text-white px-4 py-2 rounded hidden">Previous Hole</button>
                    <button id="nextHole" class="bg-blue-500 text-white px-4 py-2 rounded">Next Hole</button>
                    <button id="saveScore" class="bg-green-500 text-white px-4 py-2 rounded">Save Score</button>
                </div>
            </div>

            <div class="bg-white p-4 rounded shadow">
                <h2 class="text-xl font-semibold">Round Summary</h2>
                <div id="summary" class="mt-4"></div>
                <button id="endRound" class="bg-red-500 text-white px-4 py-2 rounded mt-4">End Round</button>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="main.js"></script>
</body>
</html>