<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="background-color:#f4f4ff;padding:24px;font-family:Arial,sans-serif;">
    <div style="max-width:600px;margin:auto;background:#ffffff;padding:24px;border-radius:8px;border:1px solid #e0e0e0;">
        <h2 style="color:#333333;">Welcome to FRWRD Tutors</h2>
        <p>Dear {{ $name }},</p>
        <p>Thank you for choosing FRWRD Tutors! We're excited to support your learning journey.</p>
        <p>To proceed, we've created an account for you in our system. Please use the login details below to access your dashboard, select your subject, book your preferred time slots, and choose from our available tutors.</p>
        
        <div style="background-color:#f9f9ff;padding:16px;border-radius:6px;margin:20px 0;border:1px dashed #7a57ff;">
            <h4 style="margin-top:0;color:#7a57ff;">🔑 Login Details:</h4>
            <p><strong>Email / Username:</strong> {{ $email }}</p>
            <p><strong>Temporary Password:</strong> {{ $password }}</p>
            <p><strong>Login Link:</strong> <a href="https://app.frwrdtutors.com/login" style="color:#7a57ff;text-decoration:none;">https://app.frwrdtutors.com/login</a></p>
        </div>

        <p style="text-align:center;margin:30px 0;">
            <a href="https://app.frwrdtutors.com/login" style="background-color:#7a57ff;color:#ffffff;padding:12px 24px;border-radius:4px;text-decoration:none;font-weight:bold;">Log In to Your Account</a>
        </p>

        <p>For your security, please update your password after logging in for the first time.</p>
        <p>If you have questions, our team is always here to assist you.</p>
        <p>Best regards,<br><strong>FRWRD Tutors Team</strong></p>
    </div>
</body>
</html>
