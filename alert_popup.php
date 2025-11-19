<?php
// alert_popup.php
?>

<div id="customAlert" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, #EA062B, #b71c1c); color: white; border-bottom: none; padding: 15px 20px;">
        <h5 class="modal-title"><i class="fas fa-heartbeat me-2"></i> BloodKonnector</h5>
        <button type="button" class="btn-close btn-close-white" id="closeAlert" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center" style="background-color: #fefefe; padding: 25px;">
        <i class="fas fa-tint fa-3x mb-3" style="color: #EA062B; animation: pulse 1.8s infinite;"></i>
        <h4 style="color: #222; font-weight: 700; margin-bottom: 15px; font-size: 22px;">Save Lives with BloodKonnector!</h4>
        <p style="font-size: 16px; color: #444; margin-bottom: 20px; line-height: 1.5;">
          Join our mission to connect blood donors and recipients seamlessly, making a real impact in saving lives.
        </p>
        <ul style="list-style: none; padding: 0; text-align: left; max-width: 400px; margin: 0 auto 15px;">
          <li style="font-size: 15px; color: #333; margin-bottom: 12px; display: flex; align-items: center;">
            <i class="fas fa-search me-2" style="color: #28a745; font-size: 16px;"></i> 
            <span><strong>Smart Search:</strong> Find donors by blood type and location instantly.</span>
          </li>
          <li style="font-size: 15px; color: #333; margin-bottom: 12px; display: flex; align-items: center;">
            <i class="fas fa-bell me-2" style="color: #3498db; font-size: 16px;"></i> 
            <span><strong>Real-Time Alerts:</strong> Stay updated with urgent donation requests.</span>
          </li>
          <li style="font-size: 15px; color: #333; margin-bottom: 12px; display: flex; align-items: center;">
            <i class="fas fa-lock me-2" style="color: #e74c3c; font-size: 16px;"></i> 
            <span><strong>Secure Platform:</strong> Your data is protected with top-tier security.</span>
          </li>
          <li style="font-size: 15px; color: #333; margin-bottom: 12px; display: flex; align-items: center;">
            <i class="fas fa-headset me-2" style="color: #f1c40f; font-size: 16px;"></i> 
            <span><strong>24/7 Support:</strong> We're here to assist you anytime.</span>
          </li>
        </ul>
      </div>
      <div class="modal-footer justify-content-center" style="border-top: none; padding: 10px 20px; background-color: #fefefe;">
        <button type="button" class="btn btn-outline-secondary" id="closeAlertFooter">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
  .modal-content {
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    background-color: #fefefe;
    border: none;
  }

  .modal-header {
    padding: 15px 20px;
    background: linear-gradient(135deg, #EA062B, #b71c1c);
    border-bottom: none;
  }

  .modal-title {
    font-weight: 700;
    font-size: 20px;
    display: flex;
    align-items: center;
    letter-spacing: 0.5px;
  }

  .modal-body {
    padding: 25px;
    background-color: #fefefe;
  }

  .modal-body i.fa-tint {
    animation: pulse 1.8s infinite;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
  }

  .modal-body h4 {
    font-size: 22px;
    color: #222;
    margin-bottom: 15px;
    font-weight: 700;
  }

  .modal-body p {
    font-size: 16px;
    color: #444;
    line-height: 1.5;
  }

  .modal-body ul li {
    display: flex;
    align-items: center;
    font-size: 15px;
    line-height: 1.6;
    transition: transform 0.2s ease;
  }

  .modal-body ul li:hover {
    transform: translateX(5px);
  }

  .modal-body ul li i {
    font-size: 16px;
    width: 20px;
    text-align: center;
  }

  .modal-footer {
    padding: 10px 20px;
    background-color: #fefefe;
    border-top: none;
  }

  .btn-outline-secondary {
    border-color: #6c757d;
    color: #6c757d;
    font-weight: 600;
    padding: 8px 20px;
    border-radius: 25px;
    transition: all 0.3s ease;
    font-size: 14px;
  }

  .btn-outline-secondary:hover {
    background-color: #6c757d;
    color: white;
  }

  @keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
  }

  @media (max-width: 768px) {
    .modal-dialog {
      margin: 12px;
      max-width: 90%;
    }

    .modal-content {
      border-radius: 12px;
    }

    .modal-title {
      font-size: 18px;
    }

    .modal-body h4 {
      font-size: 20px;
    }

    .modal-body p {
      font-size: 15px;
    }

    .modal-body ul li {
      font-size: 14px;
    }

    .modal-body ul li i {
      font-size: 15px;
    }

    .btn-outline-secondary {
      padding: 7px 18px;
      font-size: 13px;
    }
  }

  @media (max-width: 576px) {
    .modal-body {
      padding: 20px;
    }

    .modal-title {
      font-size: 16px;
    }

    .modal-body h4 {
      font-size: 18px;
    }

    .modal-body p {
      font-size: 14px;
    }

    .modal-body ul li {
      font-size: 13px;
    }

    .modal-body ul li i {
      font-size: 14px;
    }

    .btn-outline-secondary {
      padding: 6px 16px;
      font-size: 12px;
    }
  }
</style>

<script>
$(document).ready(function() {
  // Show the modal
  $('#customAlert').modal('show');

  // Auto-close after 30 seconds
  setTimeout(function() {
    $('#customAlert').modal('hide');
  }, 30000);

  // Close button functionality (header and footer)
  $('#closeAlert, #closeAlertFooter').click(function() {
    $('#customAlert').modal('hide');
  });
});
</script>