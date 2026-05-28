        document.addEventListener('DOMContentLoaded', function() {
            const verifyButton = document.getElementById('verifyButton');
            const uploadSection = document.getElementById('uploadSection');
            const accountCard = document.getElementById('accountCard');
            const copyButton = document.getElementById('copyButton');
            const uploadBtn = document.getElementById('uploadBtn');
            const fileInput = document.getElementById('fileInput');
            const fileName = document.getElementById('fileName');
            const submitArea = document.getElementById('submitArea');
            const submitText = document.getElementById('submitText');
            const confirmationSection = document.getElementById('confirmationSection');
            const doneButton = document.getElementById('doneButton');
            const uploadForm = document.getElementById('uploadForm');
            const progressBar = document.getElementById('progressBar');
            const progress = document.getElementById('progress');
            const uploadStatus = document.getElementById('uploadStatus');
            const iosWarning = document.getElementById('iosWarning');

            // Detect iOS and show warning
            function isIOS() {
                return [
                    'iPad Simulator',
                    'iPhone Simulator',
                    'iPod Simulator',
                    'iPad',
                    'iPhone',
                    'iPod'
                ].includes(navigator.platform) || 
                (navigator.userAgent.includes("Mac") && "ontouchend" in document);
            }
            
            if (isIOS()) {
                iosWarning.style.display = 'block';
            }

            // Show upload section + hide account details
            verifyButton.addEventListener('click', function() {
                accountCard.style.display = 'none';
                uploadSection.style.display = 'block';
                copyButton.style.display = 'none';
                uploadSection.scrollIntoView({behavior: 'smooth'});
            });

            // Copy account number
            copyButton.addEventListener('click', function() {
                const accountNumber = document.getElementById('acctNumb').textContent;
                navigator.clipboard.writeText(accountNumber.replace(/\s/g, '')).then(() => {
                    copyButton.innerHTML = '<i class="material-icons">check</i><div class="copy-text">Copied!</div>';
                    setTimeout(() => {
                        copyButton.innerHTML = '<i class="material-icons">content_copy</i><div class="copy-text">Copy Account Number</div>';
                    }, 2000);
                });
            });

            uploadBtn.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileName.innerHTML = `<i class="material-icons" style="vertical-align:middle;margin-right:5px;color:#4CAF50">check_circle</i>${this.files[0].name}`;
                    // Enable submit button
                    submitArea.classList.remove('disabled');
                } else {
                    fileName.textContent = 'No file chosen';
                    submitArea.classList.add('disabled');
                }
            });

            submitArea.addEventListener('click', function() {
                if (submitArea.classList.contains('disabled')) {
                    return;
                }
                
                if (fileInput.files.length === 0) {
                    alert('Please upload a receipt first');
                    return;
                }
                
                // Show upload progress
                progressBar.style.display = 'block';
                uploadStatus.style.display = 'block';
                submitArea.classList.add('disabled');
                submitText.textContent = 'Uploading...';
                
                // Create FormData object and send via AJAX
                const formData = new FormData(uploadForm);
                const xhr = new XMLHttpRequest();
                
                // Track upload progress
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progress.style.width = percentComplete + '%';
                        uploadStatus.textContent = 'Uploading... ' + Math.round(percentComplete) + '%';
                    }
                });
                
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.status) {
                                uploadSection.style.display = 'none';
                                confirmationSection.style.display = 'block';
                                confirmationSection.scrollIntoView({behavior: 'smooth'});
                            } else {
                                alert('Error: ' + data.message);
                                resetUploadUI();
                            }
                        } catch (e) {
                            alert('Error parsing server response');
                            resetUploadUI();
                        }
                    } else {
                        alert('Error uploading file. Server returned ' + xhr.status);
                        resetUploadUI();
                    }
                });
                
                xhr.addEventListener('error', function() {
                    alert('Error uploading file. Please check your connection and try again.');
                    resetUploadUI();
                });
                
                xhr.open('POST', '');
                xhr.send(formData);
            });

            function resetUploadUI() {
                progressBar.style.display = 'none';
                uploadStatus.style.display = 'none';
                progress.style.width = '0%';
                uploadStatus.textContent = 'Uploading... 0%';
                submitArea.classList.remove('disabled');
                submitText.textContent = 'Submit Verification';
            }

            doneButton.addEventListener('click', () => {
                alert('Transaction completed successfully!');
                window.location.href = 'dashboard.php'; // Redirect to dashboard or home page
            });
        });