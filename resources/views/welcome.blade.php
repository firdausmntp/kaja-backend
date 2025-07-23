<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Unauthorized</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --primary-color: #dc3545;
            --secondary-color: #6c757d;
            --dark-color: #212529;
            --light-bg: #f8f9fa;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f0f0f;
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            opacity: 0.1;
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, #dc3545 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Main Container */
        .access-denied-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Card Styling */
        .access-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 60px 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .access-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #dc3545, #fd7e14, #dc3545);
            border-radius: 20px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
            animation: borderGlow 3s ease-in-out infinite;
        }

        @keyframes borderGlow {

            0%,
            100% {
                opacity: 0.3;
            }

            50% {
                opacity: 0.8;
            }
        }

        /* Icon Container */
        .icon-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            border-radius: 50%;
            opacity: 0.2;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.95);
                opacity: 0.2;
            }

            50% {
                transform: scale(1.1);
                opacity: 0.3;
            }

            100% {
                transform: scale(0.95);
                opacity: 0.2;
            }
        }

        .icon-main {
            font-size: 60px;
            color: #dc3545;
            z-index: 1;
            animation: shake 2s ease-in-out infinite;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-2px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(2px);
            }
        }

        /* Typography */
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            background: linear-gradient(45deg, #dc3545, #fd7e14);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .error-code {
            font-size: 6rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 20px;
            letter-spacing: -0.05em;
            opacity: 0.1;
            color: #dc3545;
        }

        .description {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        /* Buttons */
        .btn-custom {
            padding: 12px 30px;
            font-weight: 500;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            margin: 0 10px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary-custom {
            background: linear-gradient(45deg, #dc3545, #fd7e14);
            color: white;
            border: none;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
            color: white;
        }

        .btn-secondary-custom {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary-custom:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
        }

        /* Additional Details */
        .details-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .detail-label {
            font-weight: 500;
        }

        .detail-value {
            font-family: 'Courier New', monospace;
            color: rgba(255, 255, 255, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .access-card {
                padding: 40px 20px;
            }

            h1 {
                font-size: 2rem;
            }

            .error-code {
                font-size: 4rem;
            }

            .btn-custom {
                display: block;
                width: 100%;
                margin: 10px 0;
            }
        }

        /* Floating Elements */
        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .floating-element:nth-child(3) {
            bottom: 20%;
            left: 15%;
            animation-delay: 4s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }
    </style>
</head>

<body>
    <!-- Animated Background -->
    <div class="bg-animation"></div>

    <!-- Floating Elements -->
    <i class="bi bi-shield-x floating-element" style="font-size: 40px; color: #dc3545;"></i>
    <i class="bi bi-lock-fill floating-element" style="font-size: 30px; color: #fd7e14;"></i>
    <i class="bi bi-exclamation-triangle floating-element" style="font-size: 35px; color: #dc3545;"></i>

    <!-- Main Container -->
    <div class="access-denied-container">
        <div class="access-card">
            <!-- Error Code -->
            <div class="error-code">403</div>

            <!-- Icon -->
            <div class="icon-container">
                <div class="icon-bg"></div>
                <i class="bi bi-shield-fill-x icon-main"></i>
            </div>

            <!-- Content -->
            <h1>Access Denied</h1>
            <p class="description">
                Sorry, you don't have permission to access this resource.
                This area is restricted to authorized personnel only.
            </p>


            <!-- Additional Details -->
            <div class="details-section">
                <div class="detail-item">
                    <span class="detail-label">Error Code:</span>
                    <span class="detail-value">HTTP_403_FORBIDDEN</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value" id="current-time"></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Request ID:</span>
                    <span class="detail-value" id="request-id"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleString();
        }
        updateTime();
        setInterval(updateTime, 1000);

        // Generate random request ID
        function generateRequestId() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '';
            for (let i = 0; i < 16; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result.match(/.{1,4}/g).join('-');
        }
        document.getElementById('request-id').textContent = generateRequestId();

        // Add hover effect to card
        const card = document.querySelector('.access-card');
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            card.style.background = `
                radial-gradient(
                    circle at ${x}px ${y}px,
                    rgba(220, 53, 69, 0.1) 0%,
                    rgba(255, 255, 255, 0.05) 50%
                )
            `;
        });

        card.addEventListener('mouseleave', () => {
            card.style.background = 'rgba(255, 255, 255, 0.05)';
        });
    </script>
</body>

</html>
