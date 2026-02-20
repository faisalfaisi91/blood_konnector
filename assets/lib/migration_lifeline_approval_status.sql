-- Add approval_status to lifeline_profiles for admin approval flow
-- Run this to enable Approve/Reject join requests in Lifeline Admin Dashboard
-- If column already exists, this script will fail - run only once.

ALTER TABLE lifeline_profiles 
ADD COLUMN approval_status ENUM('pending','approved','rejected') DEFAULT 'approved';
