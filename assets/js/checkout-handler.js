// checkout-handler.js
function handleError(errors) {
    errors.forEach(function (error) {
        console.error('Error:', error.code, error.message);
        alert('Payment failed: ' + error.message);
    });
}
