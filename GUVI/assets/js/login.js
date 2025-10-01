/**
 * js/login.js
 * Handles user login and stores the session token in localStorage via JQuery AJAX.
 * Interacts with php/login.php.
 */

const AUTH_TOKEN_KEY = 'authToken'; // Key used to save the session token

$(document).ready(function() {
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();

        const email = $('#email').val();
        const password = $('#password').val();
        const $messageDiv = $('#message').removeClass().html('');

        if (!email || !password) {
            $messageDiv.addClass('text-danger').html('Please enter email and password.');
            return;
        }

        const loginData = {
            email: email,
            password: password
        };

        $messageDiv.addClass('text-info').html('Logging in...');

        // JQuery AJAX call to the backend
        $.ajax({
            url: 'php/login.php',
            type: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify(loginData),
            
            success: function(response) {
                if (response.success) {
                    // CRITICAL: Save the session token to localStorage
                    localStorage.setItem(AUTH_TOKEN_KEY, response.session_token);
                    
                    $messageDiv.removeClass('text-info').addClass('text-success').html(response.message);
                    
                    // Redirect to the profile page
                    setTimeout(function() {
                        window.location.href = 'profile.html'; 
                    }, 500);

                } else {
                    $messageDiv.removeClass('text-info').addClass('text-danger').html(response.message);
                }
            },
            error: function() {
                $messageDiv.removeClass('text-info').addClass('text-danger').html('Login failed due to a server error.');
            }
        });
    });
});