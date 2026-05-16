<?php if (!defined('TIMER_BANNER_SHOWN')) { define('TIMER_BANNER_SHOWN', true); ?>
<!-- First banner -->
<div id="timer-banner" 
     class="fixed-top"
     style="top:0; left:0; width:100%; background:#a40000; 
            text-align:center; font-weight:bold; color:#f4f0ec; 
            z-index:1050;">
  Buy any 3 products @ 1169 within <span id="cooldown-timer">24:00:00</span>
</div>

<!-- Second banner (stacked below first one) -->
<div id="welcome-banner" 
     class="fixed-top"
     style="top:24px; left:0; width:100%; background:#1e90ff; 
            text-align:center;font-weight:bold; color:white; 
            z-index:1040;">
  WELCOME TO BLUEFIFTH
</div>




<script>
  function startCountdown() {
    let endTime = localStorage.getItem("cooldownEndTime");

    // If no timer stored, set new one for 24 hours
    if (!endTime) {
      endTime = new Date().getTime() + (24 * 60 * 60 * 1000); 
      localStorage.setItem("cooldownEndTime", endTime);
    }

    function updateTimer() {
      let now = new Date().getTime();
      let distance = endTime - now;

      if (distance <= 0) {
        // Reset timer after finishing
        endTime = new Date().getTime() + (24 * 60 * 60 * 1000);
        localStorage.setItem("cooldownEndTime", endTime);
        distance = endTime - now;
      }

      // Time calculations
      let hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      let minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      let seconds = Math.floor((distance % (1000 * 60)) / 1000);

      // Format to 2-digits
      hours = hours.toString().padStart(2, "0");
      minutes = minutes.toString().padStart(2, "0");
      seconds = seconds.toString().padStart(2, "0");

      document.getElementById("cooldown-timer").innerText = hours + ":" + minutes + ":" + seconds;
    }

    setInterval(updateTimer, 1000);
  }

  // Start on load
  startCountdown();
</script>
<?php } ?>
