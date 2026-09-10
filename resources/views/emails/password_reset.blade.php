<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="background-color:#f4f4ff;padding:24px;font-family:Arial,sans-serif;">
    <div style="max-width:600px;margin:auto;background:#ffffff;padding:24px;border-radius:8px;border:1px solid #e0e0e0;">
        <h2 style="color:#333333;">Password Reset Request</h2>
        <p>Hello,</p>
        <p>We received a request to reset your password for your FRWRD Tutors account.</p>
        
        <div style="background-color:#f9f9ff;padding:16px;border-radius:6px;margin:20px 0;border:1px dashed #7a57ff;">
            <p><strong>Your New Temporary Password:</strong> {{ $password }}</p>
            <p><strong>Login Link:</strong> <a href="https://app.frwrdtutors.com/login" style="color:#7a57ff;">https://app.frwrdtutors.com/login</a></p>
        </div>

        <p>Please log in and update your password immediately from your profile settings.</p>
        <p>If you did not request this, please contact support.</p>
        <p>Best regards,<br><strong>FRWRD Tutors Support</strong></p>
    </div>
</body>
</html>
