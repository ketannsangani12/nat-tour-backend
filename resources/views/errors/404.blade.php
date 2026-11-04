<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            text-align: center;
            max-width: 540px;
        }
        
        .error-code {
            font-size: 160px;
            font-weight: 700;
            background: linear-gradient(135deg, #264f73 0%, #60BEBD 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 16px;
        }
        
        .error-title {
            font-size: 28px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
        }
        
        .error-message {
            font-size: 16px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .illustration {
            margin-bottom: 32px;
        }
        
        .illustration svg {
            width: 200px;
            height: 200px;
        }
        
        .buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #264f73 0%, #3a6d99 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(38, 79, 115, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(38, 79, 115, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #374151;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .btn svg {
            width: 18px;
            height: 18px;
        }
        
        @media (max-width: 480px) {
            .error-code {
                font-size: 120px;
            }
            
            .error-title {
                font-size: 22px;
            }
            
            .buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="illustration">
            <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="100" cy="100" r="80" fill="#f3f4f6"/>
                <path d="M60 130 Q100 90 140 130" stroke="#264f73" stroke-width="4" stroke-linecap="round" fill="none"/>
                <circle cx="70" cy="85" r="8" fill="#264f73"/>
                <circle cx="130" cy="85" r="8" fill="#264f73"/>
                <path d="M50 60 L65 75" stroke="#60BEBD" stroke-width="3" stroke-linecap="round"/>
                <path d="M150 60 L135 75" stroke="#60BEBD" stroke-width="3" stroke-linecap="round"/>
                <circle cx="40" cy="50" r="4" fill="#60BEBD"/>
                <circle cx="160" cy="50" r="4" fill="#60BEBD"/>
                <circle cx="100" cy="40" r="3" fill="#264f73" opacity="0.5"/>
            </svg>
        </div>
        
        <h1 class="error-code">404</h1>
        <h2 class="error-title">Page Not Found</h2>
        <p class="error-message">
            {{ isset($error) ? $error : "Oops! The page you're looking for doesn't exist or has been moved." }}
        </p>
        
        <div class="buttons">
            <button type="button" class="btn btn-secondary" onclick="history.back()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Go Back
            </button>
        </div>
    </div>
</body>
</html>