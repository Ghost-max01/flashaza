        (function(){
            const phoneInput = document.getElementById('phone');
            const emailContainer = document.getElementById('email-container');
            const passwordContainer = document.getElementById('password-container');
            const signupBtn = document.getElementById('signup-btn');
            const progressBar = document.getElementById('progress-bar');
            const validationMessage = document.getElementById('validation-message');
            const phoneContainer = document.getElementById('phone-container');
            const nameDisplay = document.getElementById('name-display');
            const loginLink = document.getElementById('login-link');
            const themeToggle = document.getElementById('theme-toggle');
            const themeIcon = document.getElementById('theme-icon');
            
            // Dark mode functionality
            function initDarkMode() {
                const savedTheme = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                // Set initial theme based on saved preference or system preference
                if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                    document.body.classList.add('dark-mode');
                    themeIcon.textContent = 'light_mode';
                } else {
                    document.body.classList.remove('dark-mode');
                    themeIcon.textContent = 'dark_mode';
                }
                
                // Toggle theme when button is clicked
                themeToggle.addEventListener('click', () => {
                    if (document.body.classList.contains('dark-mode')) {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('theme', 'light');
                        themeIcon.textContent = 'dark_mode';
                    } else {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('theme', 'dark');
                        themeIcon.textContent = 'light_mode';
                    }
                });
            }
            
            // Initialize dark mode
            initDarkMode();

            // Allow only digits in phone
            phoneInput.addEventListener('keydown', function(e){
                if ([46,8,9,27,13].includes(e.keyCode) ||
                    (e.keyCode === 65 && e.ctrlKey) ||
                    (e.keyCode === 67 && e.ctrlKey) ||
                    (e.keyCode === 86 && e.ctrlKey) ||
                    (e.keyCode === 88 && e.ctrlKey) ||
                    (e.keyCode >= 35 && e.keyCode <= 39)) return;

                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) &&
                    (e.keyCode < 96 || e.keyCode > 105)) e.preventDefault();
            });

            // When phone reaches 10 digits, verify with backend (Flutterwave -> OPay)
            phoneInput.addEventListener('input', function(){
                validationMessage.style.display = 'none';
                phoneContainer.classList.remove('invalid');

                const v = phoneInput.value.replace(/\D+/g, '');
                if (v.length === 10) {
                    // Call verify
                    progressBar.style.display = 'block';
                    fetch('', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'verify_phone', phone: v })
                    })
                    .then(r => r.json())
                    .then(data => {
                        progressBar.style.display = 'none';
                        if (data.ok) {
                            // Show name and reveal the rest
                            nameDisplay.textContent = `Name: ${data.name} � Bank: ${data.bank} � Number: ${data.number}`;
                            nameDisplay.style.display = 'block';
                            emailContainer.style.display = 'block';
                            passwordContainer.style.display = 'block';
                            signupBtn.style.display = 'flex';
                            signupBtn.classList.add('active');
                        } else {
                            phoneContainer.classList.add('invalid');
                            validationMessage.textContent = data.message || 'Verification failed';
                            validationMessage.style.display = 'block';
                        }
                    })
                    .catch(() => {
                        progressBar.style.display = 'none';
                        phoneContainer.classList.add('invalid');
                        validationMessage.textContent = 'Could not verify number. Check connection.';
                        validationMessage.style.display = 'block';
                    });
                } else {
                    // Hide fields if user deletes digits
                    nameDisplay.style.display = 'none';
                    emailContainer.style.display = 'none';
                    passwordContainer.style.display = 'none';
                    signupBtn.style.display = 'none';
                    signupBtn.classList.remove('active');
                }
            });

            // Sign up click
            signupBtn.addEventListener('click', function(){
                // Only proceed if visible/active
                if (signupBtn.style.display === 'none') return;

                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value.trim();

                // Basic checks
                const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                const passOk = /^\d{6}$/.test(password);

                if (!emailOk) {
                    validationMessage.textContent = 'Please enter a valid email address.';
                    validationMessage.style.display = 'block';
                    return;
                }
                if (!passOk) {
                    validationMessage.textContent = 'Password must be 6 digits.';
                    validationMessage.style.display = 'block';
                    return;
                }

                progressBar.style.display = 'block';
                validationMessage.style.display = 'none';

                fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'register',
                        email: email,
                        password: password
                    })
                })
                .then(r => r.json())
                .then(data => {
                    progressBar.style.display = 'none';
                    if (data.ok) {
                        window.location.href = data.redirect || 'dashboard.php';
                    } else {
                        validationMessage.textContent = data.message || 'Registration failed';
                        validationMessage.style.display = 'block';
                    }
                })
                .catch(() => {
                    progressBar.style.display = 'none';
                    validationMessage.textContent = 'Network error. Please try again.';
                    validationMessage.style.display = 'block';
                });
            });

            // Go to login page
            loginLink.addEventListener('click', function(){
                window.location.href = 'login.php';
            });
        })();