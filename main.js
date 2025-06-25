// main.js - Golf Course GPS & Scoring

console.log("main.js loaded!");

let map, currentPosition, fairwayMarker;
let holeNumber = 1;
let unit = 'meters';
let courseData = null;
let userInteracted = false;
let lastPosition = null;
let csrfToken = '';
const POSITION_THRESHOLD = 10;
let roundStarted = false;
let stagedScores = {};

async function fetchCsrfToken() {
    const response = await $.get('api.php?action=get_csrf_token');
    csrfToken = response.csrf_token;
}

function resetStagedScores() {
    stagedScores = {};
    if (courseData && courseData.holes_data) {
        courseData.holes_data.forEach(h => {
            stagedScores[h.hole_number] = null;
        });
    }
}

function updateScoreSlider() {
    if (!courseData || !courseData.holes_data) return;
    const hole = courseData.holes_data.find(h => h.hole_number == holeNumber);
    const scoreSlider = document.getElementById('scoreSlider');
    if (stagedScores[holeNumber] !== null && stagedScores[holeNumber] !== undefined) {
        scoreSlider.value = stagedScores[holeNumber];
    } else {
        scoreSlider.value = hole ? hole.par : 4;
    }
    document.getElementById('scoreDisplay').textContent = scoreSlider.value;
}

function updateButtonVisibility() {
    if (!courseData) return;
    const prevHoleButton = document.getElementById('prevHole');
    const nextHoleButton = document.getElementById('nextHole');
    prevHoleButton.classList.toggle('hidden', holeNumber <= 1);
    nextHoleButton.classList.toggle('hidden', holeNumber >= courseData.holes);
}

async function updateSummary() {
    if (!courseData) return;
    const summaryDiv = document.getElementById('summary');
    let totalScore = 0;
    let summaryHTML = '';
    for (let i = 1; i <= courseData.holes; i++) {
        let score = stagedScores[i];
        if (score !== null && score !== undefined) {
            totalScore += parseInt(score);
            summaryHTML += `<p><strong>Hole ${i}</strong>: Score ${score}</p>`;
        } else {
            summaryHTML += `<p><strong>Hole ${i}</strong>: Not set</p>`;
        }
    }
    summaryHTML += `
        <h3 class="text-lg font-semibold mt-4">Total</h3>
        <p>Score: ${totalScore}</p>
    `;
    summaryDiv.innerHTML = summaryHTML;
}

async function fetchCourseData() {
    const response = await $.get('api.php?action=get_course');
    courseData = response;
    document.getElementById('courseInfo').textContent = `${courseData.location} - ${courseData.holes} Holes, Par ${courseData.par}, ${courseData.length}m`;
    updateScoreSlider();
}

async function checkRoundSession() {
    const resp = await $.get('api.php?action=check_round_session');
    if (resp.has_round) {
        $('#startNewRound').addClass('hidden');
        roundStarted = true;
        $('#gameSection').removeClass('hidden');
    } else {
        $('#startNewRound').removeClass('hidden');
        roundStarted = false;
        $('#gameSection').addClass('hidden');
    }
}

async function checkLogin() {
    const isLoggedIn = $('body').data('loggedin') === true || $('body').attr('data-loggedin') === "true";
    if (isLoggedIn) {
        $('#authSection').addClass('hidden');
        $('#userInfo').removeClass('hidden');
        await fetchCsrfToken();
        await fetchCourseData();
        await checkRoundSession();
        updateButtonVisibility();
        getLocation();
        resetStagedScores();
        await updateSummary();
    } else {
        $('#authSection').removeClass('hidden');
        $('#userInfo').addClass('hidden');
        $('#gameSection').addClass('hidden');
        $('#profileSection').addClass('hidden');
        $('#startNewRound').addClass('hidden');
    }
}

function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371e3;
    const φ1 = lat1 * Math.PI / 180;
    const φ2 = lat2 * Math.PI / 180;
    const Δφ = (lat2 - lat1) * Math.PI / 180;
    const Δλ = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
              Math.cos(φ1) * Math.cos(φ2) *
              Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

function initMap(lat, lng) {
    map = L.map('map').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    map.on('move', () => {
        userInteracted = true;
    });

    map.on('click', function(e) {
        if (fairwayMarker) map.removeLayer(fairwayMarker);
        fairwayMarker = L.marker(e.latlng).addTo(map);
        updateDistances(e.latlng);
    });
}

async function updateDistances(fairwayLatLng = null) {
    if (!currentPosition) return;
    const data = {
        player_lat: currentPosition[0],
        player_lon: currentPosition[1],
        hole_number: holeNumber,
        unit: unit,
        csrf_token: csrfToken
    };
    if (fairwayLatLng) {
        data.fairway_lat = fairwayLatLng.lat;
        data.fairway_lon = fairwayLatLng.lng;
    }

    const response = await $.post('api.php?action=calculate_distances', JSON.stringify(data));
    if (response.error) {
        alert(response.error);
        return;
    }
    document.getElementById('distanceToCurrentGreen').textContent = `From current location to green: Front ${response.front.toFixed(0)} ${unit}, Center ${response.center.toFixed(0)} ${unit}, Back ${response.back.toFixed(0)} ${unit}`;
    if (response.to_point) {
        document.getElementById('distanceToPoint').textContent = `Distance to selected point: ${response.to_point.toFixed(0)} ${unit}`;
        document.getElementById('distanceToGreen').textContent = `From point to green: Front ${response.from_point_to_green.front.toFixed(0)} ${unit}, Center ${response.from_point_to_green.center.toFixed(0)} ${unit}, Back ${response.from_point_to_green.back.toFixed(0)} ${unit}`;
    } else {
        document.getElementById('distanceToPoint').textContent = '';
        document.getElementById('distanceToGreen').textContent = '';
    }
}

function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            position => {
                const { latitude, longitude } = position.coords;
                currentPosition = [latitude, longitude];

                if (!map) {
                    initMap(latitude, longitude);
                    L.marker(currentPosition).addTo(map).bindPopup('You are here').openPopup();
                    updateDistances();
                    return;
                }

                L.marker(currentPosition).addTo(map).bindPopup('You are here').openPopup();
                updateDistances(fairwayMarker ? fairwayMarker.getLatLng() : null);

                const distanceToLast = lastPosition ? haversineDistance(currentPosition[0], currentPosition[1], lastPosition[0], lastPosition[1]) : 0;
                if (!userInteracted && (!lastPosition || distanceToLast > POSITION_THRESHOLD)) {
                    map.setView(currentPosition, map.getZoom());
                }
                lastPosition = currentPosition;
            },
            error => {
                console.error('Geolocation error:', error);
                if (!map && courseData && courseData.holes_data) {
                    initMap(courseData.holes_data[0].green_center_lat, courseData.holes_data[0].green_center_lon);
                }
                alert('Unable to access location. Using default course location.');
            }
        );
    } else {
        alert('Geolocation not supported.');
        if (!map && courseData && courseData.holes_data) {
            initMap(courseData.holes_data[0].green_center_lat, courseData.holes_data[0].green_center_lon);
        }
    }
}

// --- AUTH HANDLERS ---

$('#loginForm').submit(async function(e) {
    e.preventDefault();
    $('#loginError').hide().text('');
    const email = $('#loginEmail').val();
    const password = $('#loginPassword').val();
    try {
        const response = await $.ajax({
            url: 'api.php?action=login',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ email, password }),
            dataType: 'json'
        });
        if (response.success) {
            $('#userName').text(response.name);
            $('#authSection').addClass('hidden');
            $('#userInfo').removeClass('hidden');
            await fetchCsrfToken();
            await fetchCourseData();
            await checkRoundSession();
            updateButtonVisibility();
            getLocation();
            resetStagedScores();
            await updateSummary();
        } else {
            $('#loginError').show().text(response.error || 'Login failed.');
        }
    } catch (err) {
        $('#loginError').show().text('Network or server error.');
    }
});

$('#registerForm').submit(async function(e) {
    e.preventDefault();
    $('#registerError').hide().text('');
    const name = $('#registerName').val();
    const email = $('#registerEmail').val();
    const password = $('#registerPassword').val();
    try {
        const response = await $.post('api.php?action=register', JSON.stringify({ name, email, password }));
        if (response.success) {
            alert('Registration successful! Please log in.');
            $('#showLogin').click();
            await fetchCsrfToken();
        } else {
            $('#registerError').show().text(response.error || 'Registration failed.');
        }
    } catch (err) {
        $('#registerError').show().text('Network or server error.');
    }
});

$('#logoutButton').click(async () => {
    await $.post('api.php?action=logout');
    $('#userInfo').addClass('hidden');
    $('#gameSection').addClass('hidden');
    $('#profileSection').addClass('hidden');
    $('#authSection').removeClass('hidden');
    $('#userName').text('');
    $('#startNewRound').addClass('hidden');
    map = null;
    currentPosition = null;
    fairwayMarker = null;
    holeNumber = 1;
    roundStarted = false;
    resetStagedScores();
    await fetchCsrfToken();
});

$('#showRegister').click(() => {
    $('#loginForm').addClass('hidden');
    $('#registerForm').removeClass('hidden');
});

$('#showLogin').click(() => {
    $('#registerForm').addClass('hidden');
    $('#loginForm').removeClass('hidden');
});

// --- USER DROPDOWN PROFILE ---

$('#userDropdownBtn').on('click', function(e) {
    e.stopPropagation();
    $('#userDropdownMenu').toggleClass('hidden');
});
$(document).on('click', function() {
    $('#userDropdownMenu').addClass('hidden');
});
$('#userDropdownMenu').on('click', function(e) {
    e.stopPropagation();
});

async function showProfile() {
    const response = await $.get('api.php?action=get_profile');
    if (response.error) {
        alert(response.error);
        return;
    }
    $('#gameSection').addClass('hidden');
    $('#profileSection').removeClass('hidden');
    $('#profileEmail').text(response.email);
    $('#profileName').text(response.name);

    let historyHTML = '';
    if (response.sessions && response.sessions.length > 0) {
        response.sessions.forEach(session => {
            historyHTML += `<div class="mb-2 border-b pb-2">
                <strong>${session.course_name}</strong> - ${new Date(session.created_at).toLocaleDateString()}<br/>
                Total Score: ${session.total}<br/>
                <details>
                    <summary>View Holes</summary>
                    ${session.holes.map(h => `Hole ${h.hole_number}: ${h.score}`).join('<br/>')}
                </details>
                <div class="flex space-x-2 mt-2">
                    <button class="edit-round-btn bg-blue-500 text-white px-2 py-1 rounded text-sm" data-session="${session.session_id}">Edit</button>
                    <button class="delete-round-btn bg-red-500 text-white px-2 py-1 rounded text-sm" data-session="${session.session_id}">Delete</button>
                </div>
            </div>`;
        });
    } else {
        historyHTML = '<p>No rounds played yet.</p>';
    }
    $('#roundHistory').html(historyHTML);
}

$(document).on('click', '.edit-round-btn', function() {
    const sessionId = $(this).data('session');
    window.location.href = 'edit_round.php?session_id=' + sessionId;
});

$(document).on('click', '.delete-round-btn', async function() {
    const sessionId = $(this).data('session');
    if (!confirm('Are you sure you want to delete this round?')) return;
    const resp = await $.post('api.php?action=delete_round', JSON.stringify({ session_id: sessionId, csrf_token: csrfToken }));
    if (resp.success) {
        alert('Round deleted!');
        showProfile();
    } else {
        alert(resp.error);
    }
});

$('#viewProfile').click(() => {
    showProfile();
    $('#userDropdownMenu').addClass('hidden');
});

$('#backToGame').click(() => {
    $('#profileSection').addClass('hidden');
    if (roundStarted) {
        $('#gameSection').removeClass('hidden');
    }
});

// --- ROUND LOGIC ---

$('#startNewRound').click(async function() {
    const response = await $.post('api.php?action=start_round');
    if (response.success) {
        $('#startNewRound').addClass('hidden');
        roundStarted = true;
        $('#gameSection').removeClass('hidden');
        resetStagedScores();
        holeNumber = 1;
        document.getElementById('holeNumber').textContent = holeNumber;
        updateButtonVisibility();
        updateScoreSlider();
        await updateSummary();
    } else {
        alert(response.error || "Couldn't start a new round.");
    }
});

$('#prevHole').click(() => {
    if (holeNumber > 1) {
        holeNumber--;
        document.getElementById('holeNumber').textContent = holeNumber;
        updateButtonVisibility();
        updateScoreSlider();
        updateDistances();
        if (fairwayMarker) {
            map.removeLayer(fairwayMarker);
            fairwayMarker = null;
            document.getElementById('distanceToPoint').textContent = '';
            document.getElementById('distanceToGreen').textContent = '';
        }
    }
});

$('#nextHole').click(() => {
    if (courseData && holeNumber < courseData.holes) {
        holeNumber++;
        document.getElementById('holeNumber').textContent = holeNumber;
        updateButtonVisibility();
        updateScoreSlider();
        updateDistances();
        if (fairwayMarker) {
            map.removeLayer(fairwayMarker);
            fairwayMarker = null;
            document.getElementById('distanceToPoint').textContent = '';
            document.getElementById('distanceToGreen').textContent = '';
        }
    }
});

$('#saveScore').click(() => {
    const score = parseInt($('#scoreSlider').val());
    if (isNaN(score) || score <= 0) {
        alert('Please select a valid score.');
        return;
    }
    stagedScores[holeNumber] = score;
    updateSummary();
});

$('#endRound').click(async () => {
    if (!courseData) return;
    let missing = [];
    for (let i = 1; i <= courseData.holes; i++) {
        if (stagedScores[i] === null || stagedScores[i] === undefined) {
            missing.push(i);
        }
    }
    if (missing.length > 0) {
        alert('Please set a score for all holes before ending the round.\nMissing: ' + missing.join(', '));
        return;
    }
    const roundData = [];
    for (let i = 1; i <= courseData.holes; i++) {
        roundData.push({ hole_number: i, score: stagedScores[i] });
    }
    const response = await $.post('api.php?action=submit_round', JSON.stringify({ scores: roundData, csrf_token: csrfToken }));
    if (response.success) {
        alert('Round saved!');
        resetStagedScores();
        await updateSummary();
        holeNumber = 1;
        document.getElementById('holeNumber').textContent = holeNumber;
        updateScoreSlider();
        updateButtonVisibility();
        roundStarted = false;
        $('#gameSection').addClass('hidden');
        $('#startNewRound').removeClass('hidden');
    } else {
        alert(response.error);
    }
});

// --- UI/UX ---

$('#unitToggle').change((e) => {
    unit = e.target.checked ? 'yards' : 'meters';
    updateDistances(fairwayMarker ? fairwayMarker.getLatLng() : null);
});

$('#scoreSlider').on('input', () => {
    $('#scoreDisplay').text($('#scoreSlider').val());
});

// --- INIT ---
$(document).ready(async function() {
    await fetchCsrfToken();
    await checkLogin();
});