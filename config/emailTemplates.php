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
            'Our clinic will review your appointment shortly. Schedule: {schedule_summary} {arrival_instruction}',

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
            'Schedule: {schedule_summary} {arrival_instruction}',

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
            'Cancelled schedule: {schedule_summary} Please contact the clinic if you wish to schedule another appointment.',

        'label' => 'Appointment Status',

        'footer' =>
            'Thank you for your understanding.'
    ],

    'appointment_awaiting_deposit' => [
        'subject' => 'Appointment Accepted - Deposit Required',
        'heading' => 'Deposit Required',
        'intro' => 'The clinic has tentatively accepted your appointment request.',
        'instruction' => 'Schedule: {schedule_summary} {arrival_instruction} Please open Billing in your patient dashboard and submit the {deposit_amount} GCash deposit within {payment_deadline}. Your slot remains reserved during this period.',
        'label' => 'Appointment Status',
        'footer' => 'Your appointment becomes fully confirmed after the clinic verifies your payment.'
    ],

    'appointment_rejected' => [
        'subject' => 'Appointment Request Not Accepted',
        'heading' => 'Appointment Rejected',
        'intro' => 'The clinic was unable to accept your appointment request.',
        'instruction' => 'Requested schedule: {schedule_summary} The reason provided by the clinic is shown below. You may submit another appointment request.',
        'label' => 'Reason',
        'footer' => 'Please contact the clinic if you need assistance.'
    ],

    'payment_rejected' => [
        'subject' => 'Deposit Proof Needs Correction',
        'heading' => 'Payment Proof Rejected',
        'intro' => 'The clinic could not verify your submitted GCash payment proof.',
        'instruction' => 'Schedule: {schedule_summary} {arrival_instruction} Review the reason below and upload corrected proof for the {deposit_amount} deposit within {payment_deadline}.',
        'label' => 'Reason',
        'footer' => 'The expiration timer is paused again after corrected proof is submitted.'
    ],

    'appointment_confirmed_code' => [
        'subject' => 'Appointment Confirmed - Your Check-in Code',
        'heading' => 'Appointment Confirmed',
        'intro' => 'Your deposit has been verified and your appointment is now confirmed.',
        'instruction' => 'Schedule: {schedule_summary} {arrival_instruction} Present the appointment code below to the front desk.',
        'label' => 'Appointment Code',
        'footer' => 'Keep this code private and bring it with you to the clinic.'
    ],

    'reschedule_requested' => [
        'subject' => 'Reschedule Request Received',
        'heading' => 'Reschedule Request Received',
        'intro' => 'Your preferred replacement schedule has been sent to the clinic for approval.',
        'instruction' => 'Requested schedule: {requested_schedule} The clinic has until {approval_deadline} to review it. Your current appointment remains unchanged until approval.',
        'label' => 'Request Status',
        'footer' => 'You will receive another notification when the clinic responds.'
    ],

    'reschedule_approved' => [
        'subject' => 'Reschedule Approved',
        'heading' => 'Your Appointment Was Rescheduled',
        'intro' => 'The clinic approved your reschedule request.',
        'instruction' => 'New schedule: {schedule_summary} {arrival_instruction}',
        'label' => 'Request Status',
        'footer' => 'Your appointment code, selected services, and deposit remain unchanged.'
    ],

    'reschedule_rejected' => [
        'subject' => 'Reschedule Request Not Approved',
        'heading' => 'Reschedule Request Rejected',
        'intro' => 'The clinic was unable to approve your requested replacement schedule.',
        'instruction' => 'Requested schedule: {requested_schedule} Your original appointment remains confirmed.',
        'label' => 'Reason',
        'footer' => 'You may submit another reschedule request if another eligible schedule is available.'
    ],

    'reschedule_withdrawn' => [
        'subject' => 'Reschedule Request Withdrawn',
        'heading' => 'Reschedule Request Withdrawn',
        'intro' => 'Your pending reschedule request has been withdrawn.',
        'instruction' => 'Your original appointment remains confirmed: {schedule_summary}',
        'label' => 'Request Status',
        'footer' => 'No changes were made to your appointment.'
    ],

    'reschedule_expired' => [
        'subject' => 'Reschedule Request Expired',
        'heading' => 'Reschedule Request Expired',
        'intro' => 'The clinic did not complete the review within the 24-hour approval window.',
        'instruction' => 'Requested schedule: {requested_schedule} Your original appointment remains confirmed.',
        'label' => 'Request Status',
        'footer' => 'You may submit another reschedule request if an eligible schedule is available.'
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
