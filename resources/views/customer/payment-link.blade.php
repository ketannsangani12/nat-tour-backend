<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Successful</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background: #f5f8fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: #fff;
            padding: 2rem 3rem;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            text-align: center;
        }
        .card h1 {
            color: #28a745;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .card p {
            color: #555;
            font-size: 1.1rem;
            margin-top: 0;
        }
        .card .icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .btn {
            margin-top: 1.5rem;
            background: #28a745;
            color: #fff;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Payment Successful!</h1>
        <p>Thank you for your payment. Your transaction was completed successfully.</p>
        @if(!empty($cart_id))
        <a href="{{ route('customer.payment-link-tour-booking-info', ['bookingId' => $cart_id]) }}" class="btn">View Receipt</a>
        @else
        <a href="{{ route('customer.tour-booking-info', ['bookingId' => $tour_booking_id]) }}" class="btn">View Receipt</a>
        @endif
    </div>
</body>
</html>