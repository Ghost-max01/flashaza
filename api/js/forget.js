        // Theme management
        function initTheme() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            // Set initial theme based on saved preference or system preference
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.body.classList.add('dark-mode');
                document.getElementById('theme-icon').textContent = 'light_mode';
            } else {
                document.body.classList.remove('dark-mode');
                document.getElementById('theme-icon').textContent = 'dark_mode';
            }
            
            // Toggle theme when button is clicked
            document.getElementById('theme-toggle').addEventListener('click', () => {
                if (document.body.classList.contains('dark-mode')) {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                    document.getElementById('theme-icon').textContent = 'dark_mode';
                } else {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                    document.getElementById('theme-icon').textContent = 'light_mode';
                }
            });
        }
        
        // Initialize theme
        initTheme();

        // Step management
        function showStep(stepNumber) {
            // Hide all steps
            document.querySelectorAll('.step').forEach(step => {
                step.classList.remove('active');
            });
            
            // Show the selected step
            document.getElementById(`step${stepNumber}`).classList.add('active');
            
            // Update progress bars
            document.getElementById('progress1').style.width = stepNumber >= 1 ? '33%' : '0%';
            document.getElementById('progress2').style.width = stepNumber >= 2 ? '66%' : '0%';
            document.getElementById('progress3').style.width = stepNumber >= 3 ? '100%' : '0%';
            
            // Clear messages
            document.querySelectorAll('.message').forEach(msg => {
                msg.style.display = 'none';
                msg.textContent = '';
            });
        }
        
        // Show message
        function showMessage(step, message, isError = true) {
            const messageEl = document.getElementById(`message${step}`);
            messageEl.textContent = message;
            messageEl.className = isError ? 'message error' : 'message success';
            messageEl.style.display = 'block';
        }
        
        // Set up button event listeners
        document.getElementById('continueBtn').addEventListener('click', function() {
            const email = document.getElementById('emailInput').value;
            if (email && isValidEmail(email)) {
                this.disabled = true;
                this.textContent = 'Sending...';
                
                // Send request to server
                const formData = new FormData();
                formData.append('action', 'request_reset');
                formData.append('email', email);
                
                fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(1, data.message, false);
                        setTimeout(() => showStep(2), 1500);
                    } else {
                        showMessage(1, data.message);
                    }
                    this.disabled = false;
                    this.textContent = 'Continue';
                })
                .catch(error => {
                    showMessage(1, 'Network error. Please try again.');
                    this.disabled = false;
                    this.textContent = 'Continue';
                });
            } else {
                showMessage(1, 'Please enter a valid email address');
            }
        });
        
        document.getElementById('verifyOtpBtn').addEventListener('click', function() {
            const code = document.getElementById('otpInput').value;
            if (code.length === 4) {
                this.disabled = true;
                this.textContent = 'Verifying...';
                
                // Send request to server
                const formData = new FormData();
                formData.append('action', 'verify_code');
                formData.append('code', code);
                
                fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(2, data.message, false);
                        setTimeout(() => showStep(3), 1500);
                    } else {
                        showMessage(2, data.message);
                    }
                    this.disabled = false;
                    this.textContent = 'Verify Code';
                })
                .catch(error => {
                    showMessage(2, 'Network error. Please try again.');
                    this.disabled = false;
                    this.textContent = 'Verify Code';
                });
            } else {
                showMessage(2, 'Please enter a valid 4-digit code');
            }
        });
        
        document.getElementById('confirmBtn').addEventListener('click', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword.length !== 6 || !/^\d+$/.test(newPassword)) {
                showMessage(3, 'Password must be exactly 6 digits');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showMessage(3, 'Passwords do not match');
                return;
            }
            
            this.disabled = true;
            this.textContent = 'Resetting...';
            
            // Send request to server
            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('password', newPassword);
            formData.append('confirm_password', confirmPassword);
            
            fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(3, data.message, false);
                    // Redirect to login page after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showMessage(3, data.message);
                    this.disabled = false;
                    this.textContent = 'Confirm';
                }
            })
            .catch(error => {
                showMessage(3, 'Network error. Please try again.');
                this.disabled = false;
                this.textContent = 'Confirm';
            });
        });
        
        // Email validation function
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        // Initialize progress bars
        showStep(1);