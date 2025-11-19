<?php
session_start();
include('openconn.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = $_SESSION['user_id'];
    $recipient_id = mysqli_real_escape_string($conn, $_POST['recipient_id']);
    $message = trim($_POST['message']);

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit();
    }

    // Verify valid message pair by checking both users' roles
    $query = "SELECT 
        (SELECT COUNT(*) FROM recipients WHERE user_id = ?) as sender_is_recipient,
        (SELECT COUNT(*) FROM donors WHERE user_id = ?) as sender_is_donor,
        (SELECT COUNT(*) FROM recipients WHERE user_id = ?) as recipient_is_recipient,
        (SELECT COUNT(*) FROM donors WHERE user_id = ?) as recipient_is_donor";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ssss", $sender_id, $sender_id, $recipient_id, $recipient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $roles = $result->fetch_assoc();
        
        $valid = ($roles['sender_is_recipient'] && $roles['recipient_is_donor']) || 
                 ($roles['sender_is_donor'] && $roles['recipient_is_recipient']);

        if ($valid) {
            // Insert the user's message.
            $query = "INSERT INTO messages (sender_id, recipient_id, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("sss", $sender_id, $recipient_id, $message);
                $user_message_success = $stmt->execute();

                // Check if the message is one of the suggestions
                $suggestions = [
    // Donor Registration & Information
    "How can I donate blood?" => "To donate blood, simply sign up on our platform and provide your blood group and location. We'll connect you with recipients in need.",
    "What are the requirements for donating blood?" => "To donate blood, you should be at least 18 years old, in good health, and weigh more than 50 kg. Additionally, make sure you haven't donated blood in the past 3 months.",
    "Can I donate blood if I am on medication?" => "It depends on the medication you're taking. Please check with a healthcare professional or refer to our donation guidelines for more details.",
    "How often can I donate blood?" => "You can donate whole blood once every 56 days, or about every 8 weeks. For platelets or plasma, the donation frequency may vary.",

    // Recipient Information & Blood Requests
    "I need blood urgently, how can I find a donor?" => "Please provide your blood type and location, and we’ll help connect you with the nearest available donor. We prioritize urgent requests.",
    "How do I request blood on Blood Konnector?" => "You can request blood by signing up on the platform, specifying your blood group and location, and our system will match you with suitable donors.",
    "What should I do if I cannot find a donor on the platform?" => "If you can’t find a donor, our chatbot will suggest nearby blood donation centers or emergency options. You can also try extending your search radius.",

    // Blood Type Matching
    "Can I donate blood to someone with a different blood group?" => "Blood donation compatibility depends on blood types. For example, type O negative is a universal donor, while type AB positive can receive from any blood group. Let me know the blood type and I can confirm compatibility.",
    "What are the blood types that can donate to me?" => "If you’re AB+, you can receive blood from any group (A, B, AB, O). If you’re O-, you can only receive blood from O- donors. Let me know your blood type for more details!",

    // Platform Usage & Features
    "How does Blood Konnector work?" => "Blood Konnector uses AI to match blood donors with recipients based on blood type and location. It connects you with those in need and makes donation easy and safe.",
    "Is my information safe on this platform?" => "Yes, we prioritize your privacy and use encryption to secure your personal data. Your information is only shared with users involved in the donation process.",
    "Can I track the status of my donation request?" => "Yes, once you request blood, you’ll receive updates on your request status and any available donors in your area.",

    // Emergency Situations
    "What should I do if I need blood in an emergency?" => "In an emergency, please provide your blood type and location. We’ll immediately search for the closest donors and help you connect with them. You can also reach out to nearby blood banks if necessary.",
    "How quickly can I get blood?" => "The time it takes depends on the availability of donors in your area. Our platform helps connect you with the nearest donor as quickly as possible.",

    // General Questions About Blood Donation
    "Why is blood donation important?" => "Blood donation saves lives by providing blood for surgeries, trauma care, cancer treatment, and more. Every donation can help multiple people in need.",
    "Can I donate blood if I have a health condition?" => "It depends on the condition. Please consult with a healthcare provider before donating if you have any health concerns. You can also check our guidelines for specific conditions.",
    "How long does a blood donation take?" => "The blood donation process usually takes around 10-15 minutes. However, the entire visit to the donation center may take 30-45 minutes, including registration and recovery time.",

    // Donor and Recipient Matching
    "How does the platform match donors and recipients?" => "Our AI-powered system matches donors with recipients based on blood type, location, and urgency of the request. We ensure the most suitable donor is selected to help save a life.",
    "Can I specify the location where I want to donate blood?" => "Yes, when registering, you can specify your location, and we’ll match you with recipients in need within that area.",

    // Platform Support & Assistance
    "How can I get help if I face issues on the platform?" => "If you need assistance, you can reach out to our support team via chat or email. We’ll help resolve any issues you encounter.",
    "What should I do if the chatbot doesn't understand my request?" => "If the chatbot doesn't understand your query, please try rephrasing it or contact our support team for direct help.",

    // Additional Features
    "Can I set up blood donation reminders?" => "Yes, you can set up reminders for future blood donations or be notified when your blood group is in demand.",
    "Does the platform support multiple languages?" => "Yes, our platform is expanding to include multiple languages. Let us know if you need assistance in a specific language!"
];

                $auto_response = null;
                foreach ($suggestions as $question => $response) {
                    if (trim($message) === trim($question)) {
                        $auto_response = $response;
                        break;
                    }
                }

                // If an auto-response is found, insert it as a message from Blood Konnection (system or recipient)
                if ($auto_response && $user_message_success) {
                    $system_sender_id = $recipient_id; // Using recipient_id as the system sender for simplicity
                    $query = "INSERT INTO messages (sender_id, recipient_id, message) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("sss", $system_sender_id, $sender_id, $auto_response);
                        $auto_response_success = $stmt->execute();
                    }
                }

                if ($user_message_success) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid chat pair']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
}
?>