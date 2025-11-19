<div class="preloader">
  <div class="blood-droplets"></div>
  <div class="logo">
    <img src="assets/images/favicon.png" alt="Blood Konnector Logo" class="logo-img">
  </div>
  <h1>BLOOD KONNECTOR</h1>
  <div class="preload-progress">
    <span></span>
  </div>
</div>

<style>
  .preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #1a0000;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    color: #fff;
    font-family: 'Arial', sans-serif;
    overflow: hidden;
  }

  .blood-droplets {
    position: absolute;
    width: 100%;
    height: 100%;
    pointer-events: none;
  }

  .blood-droplets::before,
  .blood-droplets::after,
  .blood-droplets span:nth-child(2),
  .blood-droplets span:nth-child(3) {
    content: '';
    position: absolute;
    width: 10px;
    height: 20px;
    background: #ff3333;
    border-radius: 50% 50% 0 0;
    animation: fall 4s infinite linear;
  }

  .blood-droplets::before {
    left: 20%;
    top: -20px;
    animation-delay: 0s;
  }

  .blood-droplets::after {
    left: 60%;
    top: -40px;
    animation-delay: 1s;
  }

  .blood-droplets span:nth-child(2) {
    left: 40%;
    top: -30px;
    animation-delay: 0.5s;
  }

  .blood-droplets span:nth-child(3) {
    left: 80%;
    top: -50px;
    animation-delay: 1.5s;
  }

  @keyframes fall {
    0% { transform: translateY(-100vh); opacity: 1; }
    70% { opacity: 1; }
    100% { transform: translateY(100vh); opacity: 0; }
  }

  .logo {
    animation: pulse 2s infinite;
  }

  .logo-img {
    width: 150px;
    height: auto;
  }

  h1 {
    font-size: 2.5em;
    margin: 20px 0;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #ff3333;
  }

  .preload-progress {
    width: 200px;
    height: 10px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 5px;
    overflow: hidden;
  }

  .preload-progress span {
    display: block;
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, #ff3333, #ff6666);
    animation: progress 3s infinite ease-in-out;
  }

  @keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
  }

  @keyframes progress {
    0% { width: 0; }
    50% { width: 100%; }
    100% { width: 0; }
  }
</style>