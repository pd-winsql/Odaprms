<?php

return [

    'register' => [

        'subject' => 'Verify Your Email Address',

        'heading' => 'Email Verification',

        'intro' =>
            'Thank you for creating an account with Dr. Aprille Ventura Clinica Dental.',

        'instruction' =>
            'Use the verification code below to verify your email address.',

        'label' => 'Verification Code',

        'footer' =>
            'If you did not create this account, you may safely ignore this email.'
    ],

    'forgot_password' => [

        'subject' => 'Password Reset',

        'heading' => 'Password Reset',

        'intro' =>
            'We received a request to reset your password.',

        'instruction' =>
            'Use the code below to continue resetting your password.',

        'label' => 'Password Reset Code',

        'footer' =>
            'If you did not request a password reset, you may ignore this email.'
    ],

    'appointment_pending' => [

        'subject' => 'Appointment Received',

        'heading' => 'Appointment Received',

        'intro' =>
            'Your appointment request has been received successfully.',

        'instruction' =>
            'Our clinic will review your appointment shortly.',

        'label' => 'Appointment Status',

        'footer' =>
            'Please wait for confirmation from the clinic.'
    ],

    'appointment_confirmed' => [

        'subject' => 'Appointment Confirmed',

        'heading' => 'Appointment Confirmed',

        'intro' =>
            'Great news! Your appointment has been confirmed.',

        'instruction' =>
            'Please arrive early for your appointment. Queue positions may change when clinic staff need to prioritize another ready patient.',

        'label' => 'Appointment Status',

        'footer' =>
            'We look forward to seeing you!'
    ],

    'appointment_cancelled' => [

        'subject' => 'Appointment Cancelled',

        'heading' => 'Appointment Cancelled',

        'intro' =>
            'Unfortunately, your appointment has been cancelled.',

        'instruction' =>
            'Please contact the clinic if you wish to schedule another appointment.',

        'label' => 'Appointment Status',

        'footer' =>
            'Thank you for your understanding.'
    ],

    'appointment_awaiting_deposit' => [
        'subject' => 'Appointment Accepted - Deposit Required',
        'heading' => 'Deposit Required',
        'intro' => 'The clinic has tentatively accepted your appointment request.',
        'instruction' => 'Please open Billing in your patient dashboard and submit the ₱400 GCash deposit within eight hours. Your slot remains reserved during this period.',
        'label' => 'Appointment Status',
        'footer' => 'Your appointment becomes fully confirmed after the clinic verifies your payment.'
    ],

    'appointment_rejected' => [
        'subject' => 'Appointment Request Not Accepted',
        'heading' => 'Appointment Rejected',
        'intro' => 'The clinic was unable to accept your appointment request.',
        'instruction' => 'The reason provided by the clinic is shown below. You may submit another appointment request.',
        'label' => 'Reason',
        'footer' => 'Please contact the clinic if you need assistance.'
    ],

    'payment_rejected' => [
        'subject' => 'Deposit Proof Needs Correction',
        'heading' => 'Payment Proof Rejected',
        'intro' => 'The clinic could not verify your submitted GCash payment proof.',
        'instruction' => 'Review the reason below and upload corrected proof within eight hours.',
        'label' => 'Reason',
        'footer' => 'The expiration timer is paused again after corrected proof is submitted.'
    ],

    'appointment_confirmed_code' => [
        'subject' => 'Appointment Confirmed - Your Check-in Code',
        'heading' => 'Appointment Confirmed',
        'intro' => 'Your deposit has been verified and your appointment is now confirmed.',
        'instruction' => 'Present the appointment code below to the front desk on your appointment date.',
        'label' => 'Appointment Code',
        'footer' => 'Keep this code private and bring it with you to the clinic.'
    ],

    'staff_account_created' => [

        'subject' => 'Your Dental Assistant Account Has Been Created',

        'heading' => 'Account Created',

        'intro' =>
            'An account has been created for you at Dr. Aprille Ventura Clinica Dental.',

        'instruction' =>
            'Use your email address and the temporary password below to log in. For security, please change your password after your first login.',

        'footer' =>
            'If you were not expecting this account, please contact the clinic.'
    ]

];
