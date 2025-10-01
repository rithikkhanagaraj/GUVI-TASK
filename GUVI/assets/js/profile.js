/**
 * js/profile.js
 * * Implements authentication check via localStorage (authToken) and
 * uses JQuery AJAX to load and update profile data with php/profile.php.
 * * CRITICAL: Sends the authToken in the Authorization header for validation by Redis in the backend.
 */

const AUTH_TOKEN_KEY = 'authToken'; 

$(document).ready(function() {
    // --- 1. Authentication Check & Data Load on Page Ready ---
    const authToken = localStorage.getItem(AUTH_TOKEN_KEY);

    if (!authToken) {
        console.log("No authorization token found. Redirecting to login.");
        alert("Your session has expired or you need to log in.");
        window.location.href = 'login.html';
        return; 
    }

    // Function to load the user's profile data
    loadProfile(authToken);

    // --- 2. Profile Update Form Submission Handler ---
    $('#profileForm').on('submit', function(e) {
        e.preventDefault(); 
        updateProfile(authToken);
    });
});

/**
 * Loads the user's profile details from the backend (MongoDB via profile.php).
 * @param {string} token - The authorization token from localStorage.
 */
function loadProfile(token) {
    const $messageDiv = $('#message').removeClass().html('Loading profile...');
    
    $.ajax({
        url: 'php/profile.php',
        type: 'GET', 
        dataType: 'json',
        contentType: 'application/json',
        // CRUCIAL: Send the session token in the Authorization header
        headers: {
            'Authorization': 'Bearer ' + token 
        },
        success: function(response) {
            if (response.success) {
                const profile = response.profile || {};
                $messageDiv.html(''); 

                // Populate form fields with data received
                $('#username').val(response.username || 'N/A'); 
                $('#age').val(profile.age || '');
                $('#dob').val(profile.dob || '');
                $('#contact').val(profile.contact || '');
                
            } else if (response.message && response.message.includes('Unauthorized')) {
                handleUnauthorized();
            } else {
                $('#message').addClass('text-info').html(response.message || 'Profile data is incomplete. Please fill in the details.');
                // Always try to show the username if the backend sends it
                $('#username').val(response.username || 'N/A'); 
            }
        },
        error: function(xhr) {
            handleAjaxError(xhr);
        }
    });
}


/**
 * Sends updated profile details to the backend (MongoDB via profile.php).
 * @param {string} token - The authorization token from localStorage.
 */
function updateProfile(token) {
    const profileData = {
        age: $('#age').val(),
        dob: $('#dob').val(),
        contact: $('#contact').val()
    };

    const $messageDiv = $('#message').removeClass().html('Updating profile...');
    
    $.ajax({
        url: 'php/profile.php',
        type: 'POST', 
        dataType: 'json',
        contentType: 'application/json',
        data: JSON.stringify(profileData), 
        // CRUCIAL: Send the session token in the Authorization header
        headers: {
            'Authorization': 'Bearer ' + token
        },
        success: function(response) {
            if (response.success) {
                $messageDiv.addClass('text-success alert alert-success').html(response.message || 'Profile updated successfully!');
            } else if (response.message && response.message.includes('Unauthorized')) {
                handleUnauthorized();
            } else {
                $messageDiv.addClass('text-danger alert alert-danger').html(response.message || 'Profile update failed.');
            }
        },
        error: function(xhr) {
            handleAjaxError(xhr);
        }
    });
}

/**
 * Handles error response codes from the server, specifically 401 Unauthorized.
 */
function handleAjaxError(xhr) {
    if (xhr.status === 401) {
        handleUnauthorized();
    } else {
        const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'An unexpected server error occurred.';
        $('#message').removeClass().addClass('text-danger alert alert-danger').html('Error: ' + errorMsg);
        console.error("Server Error:", xhr.responseText);
    }
}

/**
 * Clears the session token and redirects to the login page.
 */
window.logout = function() {
    localStorage.removeItem(AUTH_TOKEN_KEY);
    window.location.href = 'login.html'; 
};

function handleUnauthorized() {
    localStorage.removeItem(AUTH_TOKEN_KEY);
    alert("Session expired or unauthorized. Please log in again.");
    window.location.href = 'login.html';
}