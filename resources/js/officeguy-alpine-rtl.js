function officeGuyPayment() {
    return {
        rtl: document.documentElement.getAttribute('dir') === 'rtl' || document.body.classList.contains('rtl'),
        errors: [],
        selectedToken: 'new',
        cardNumber: '',
        expMonth: '',
        expYear: '',
        cvv: '',
        citizenId: '',
        paymentsCount: 1,
        savePaymentMethod: false,
        singleUseToken: '',
        togglePaymentFields() {
            this.errors = [];
        }
    };
}
window.officeGuyPayment = officeGuyPayment;
