/**
 * FILE: js/register.js
 * Handles user registration via JQuery AJAX.
 */

// Check if JQuery is loaded before attempting to use it
if (typeof jQuery === 'undefined') {
    console.error("JQuery is not loaded! Please ensure the JQuery <script> tag is included before this file.");
} else {
    $(document).ready(function() {
        $('#registerForm').on('submit', function(e) {
            // CRITICAL: Stop the browser from submitting the form traditionally
            e.preventDefault(); 

            // Get values from the form inputs
            const username = $('#username').val();
            const email = $('#email').val();
            const password = $('#password').val();
            
            // Reference the message div
            const $messageDiv = $('#message').removeClass().html('');
            
            // Basic frontend validation
            if (!username || !email || !password) {
                $messageDiv.addClass('text-danger').html('Please fill in all fields.');
                return;
            }

            const registrationData = {
                username: username,
                email: email,
                password: password
            };

            $messageDiv.addClass('text-info').html('Registering...');

            // JQuery AJAX call to the backend
            $.ajax({
                url: 'php/register.php',
                type: 'POST',
                dataType: 'json',
                // Tell the server we are sending JSON
                contentType: 'application/json', 
                data: JSON.stringify(registrationData),
                
                success: function(response) {
                    if (response.success) {
                        $messageDiv.removeClass('text-info').addClass('text-success').html(response.message);
                        // Redirect to login page after a short delay
                        setTimeout(function() {
                            window.location.href = 'login.html';
                        }, 2000);
                    } else {
                        $messageDiv.removeClass('text-info').addClass('text-danger').html(response.message);
                    }
                },
                error: function(xhr, status, error) {
                    // This runs if the request fails (e.g., 404 Not Found, 500 Internal Server Error)
                    console.error("AJAX Error:", status, error);
                    $messageDiv.removeClass('text-info').addClass('text-danger').html('Server connection error. Check XAMPP and console.');
                }
            });
        });
    });
}