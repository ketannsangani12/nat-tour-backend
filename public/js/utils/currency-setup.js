// currency-setup.js
let globalCurrencyOptions = {
    symbol: "$",
    separator: ",",
    decimal: ".",
    precision: 2,
};

window.setGlobalCurrencyOptions = function (options = {}) {
    globalCurrencyOptions = Object.assign({}, globalCurrencyOptions, options);
};

(function () {
    const originalFormat = currency.prototype.format;

    currency.prototype.format = function () {
        // Temporarily update settings before formatting
        const originalSettings = this.settings;
        const mergedSettings = Object.assign({}, globalCurrencyOptions, originalSettings);

        // Call the original format function on the new instance
        return originalFormat.call(new currency(this.value, mergedSettings));
    };
})();
