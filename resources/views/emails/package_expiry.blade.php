<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="background-color:#f4f4ff;padding:20px;font-family:Arial,sans-serif;">
    <div style="max-width:600px;margin:auto;background:#ffffff;padding:25px;border-radius:8px;border:1px solid #e0e0e0;">
        <h2 style="color:#4a4a4a;">📅 Class Ending Soon – {{ $daysLeft }} Day{{ $daysLeft > 1 ? 's' : '' }} Left</h2>
        <p>Dear Student,</p>
        <p>Your tutoring package has <strong>{{ $daysLeft }} day{{ $daysLeft > 1 ? 's' : '' }}</strong> remaining before completion.</p>
        <p>To continue without interruption, please renew your tutoring package or schedule upcoming sessions from your student portal.</p>
        
        <p style="text-align:center;margin:30px 0;">
            <a href="https://app.frwrdtutors.com/dashboard" style="background-color:#7a57ff;color:#ffffff;padding:12px 24px;border-radius:4px;text-decoration:none;font-weight:bold;">Go to Dashboard</a>
        </p>

        <p>Best regards,<br><strong>FRWRD Tutors</strong></p>
    </div>
</body>
</html>
