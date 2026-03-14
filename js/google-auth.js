// Google Sign-In API initialization
function initGoogleAuth() {
    // This function would be called when the Google API is loaded
    console.log("Google Auth API initialized");
    
    // Render the Google Sign-In button
    google.accounts.id.initialize({
        client_id: '340757900430-i8nl6l45ndveq9jmbvbah7ugquauj803.apps.googleusercontent.com', // Replace with actual client ID
        callback: handleGoogleSignIn,
        auto_select: false,
        cancel_on_tap_outside: true
    });
    
    // Display the One Tap UI
    google.accounts.id.prompt();
    
    // Render button in container
    google.accounts.id.renderButton(
        document.getElementById('google-signin-button'), 
        { theme: 'filled_blue', size: 'large', shape: 'pill', width: 250 }
    );
}

// Handle Google Sign-In
function handleGoogleSignIn(response) {
    // This function is called when user signs in with Google
    if (response.credential) {
        // Send ID token to backend for verification
        fetch('/auth/google-callback.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_token: response.credential
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirect or update UI
                window.location.href = '/index.php';
            } else {
                console.error('Login failed:', data.message);
                alert('Login failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred during sign in. Please try again.');
        });
    }
}

// Load Google API
function loadGoogleAPI() {
    const script = document.createElement('script');
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.defer = true;
    script.onload = initGoogleAuth;
    document.head.appendChild(script);
}

// Initialize when the document is loaded
document.addEventListener('DOMContentLoaded', loadGoogleAPI);