
$(document).ready(function(){
	
	var url 	  = $("#baseurl").val();
	var invoiceid = $("#invoiceid").val();
	var second    = 305;

		function paymentCek(url,invoiceid,seconds,second) { 
		 
		  return $.ajax({
			url: url + "/modules/gateways/callback/shopeepay_callback.php?invoiceid=" + invoiceid,
			success: function(statuscode){
				if(statuscode == 1) {
					$('.barcode').css('display','none');
					$('.boxloading').css('display','none');
					$('.boxfailed').css('display','none');
					$('.boxtimeout').css('display','none');
					$('.boxsuccess').css('display','block');
	
					setTimeout(
						function(){
							window.location = "cart.php?a=complete"; 
						},
					1000);
	
					return false;
				}
				  
				if(statuscode == 2) {
					if(second == 305) {
						$('.barcode').css('display','none');
						$('.boxsuccess').css('display','none');
						$('.boxloading').css('display','none');
						$('.boxfailed').css('display','none');
						$('.boxtimeout').css('display','block');
					}
				}
	
				if(statuscode == 3) {
					console.log('payment refund');
				}
	
				if(statuscode == 4) {
					console.log('payment cancel');
				}
	
				if(statuscode == 5) {
					$('.barcode').css('display','none');
					$('.boxsuccess').css('display','none');
					$('.boxfailed').css('display','none');
					$('.boxtimeout').css('display','none');
					$('.boxloading').css('display','block');
				}
	
				if(statuscode == 3) {
					$('.barcode').css('display','none');
					$('.boxsuccess').css('display','none');
					$('.boxloading').css('display','none');
					$('.boxtimeout').css('display','none');
					$('.boxfailed').css('display','block');
					return false;
				}
				
				if(!$.trim(statuscode)) {
					$('.barcode').css('display','none');
					$('.boxsuccess').css('display','none');
					$('.boxloading').css('display','none');
					$('.boxfailed').css('display','none');
					$('.boxtimeout').css('display','block');
				}
	
				if(second >= 300){
					document.getElementById("countdown").innerHTML = "Timeout";
				  } else {
					document.getElementById("countdown").innerHTML = seconds + " seconds remaining";
				  }
			}
		  })
	    }
	
	  
	  
	  var i = 1;
	  var run = setInterval( async () => {
		if(second > 0) {
			await paymentCek(url,invoiceid,second,i)
			window.addEventListener('offline', () => {
				$('.barcode').css('display','none');
				$('.boxsuccess').css('display','none');
				$('.boxloading').css('display','none');
				$('.boxtimeout').css('display','block');
				$('.boxfailed').css('display','none');
				clearInterval(run)
			})
		}
		second -= 1;
		i += 1;
	  }, 1000)

	  


});

