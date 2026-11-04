    /**
     * CreditCardReader
     * Add Event to Web Page to parse the input from a usb crdit card reader
     * Note: tested with a MagTek Part Number: 
     * 
     * Example usage: 
     * Assign the form id elements before you init the class. 
     * const ccreader = new CreditCardReader({ num_id: 'ccnumber', 
     *                                         name_id: "ccname", 
     *                                         date_id: "ccdate", 
     *                                         type_id: 'cctype', 
     *                                         valid_id: "ccvalid",  
     *                                         });
    */
    class CreditCardReader {

        /**
         * @function constructor
         * @description Init the card reader value and events
         * @return void
        */
        constructor(props) {
            this.value = "";
            this.creditcard = false;
            this.getElements(props);
        }


        /**
         * @function getElements
         * @description Assign all the form elements from the page to populate 
         * @return void
        */
        getElements(props) {

            // dom ids 
            const { num_id, date_id, name_id, type_id, valid_id } = props;

            // form fields
            this.cc_num_input = document.getElementById(num_id);
            this.cc_name_input = document.getElementById(name_id);
            this.cc_date_input = document.getElementById(date_id);

            // html elements
            this.cc_type_ele = document.getElementById(type_id);
            this.cc_valid_ele = document.getElementById(valid_id);

            //assign listeners
            this.eventListeners();
        }


        /**
         * @function assignValue
         * @description Append to the input string if not key input of (Shift, Enter)
         * @return string
        */
        assignValue(key) {

            switch (key) {
                case "Shift":
                case "Enter":
                    return ""
                    break;

                default:
                    return key;
                    break;
            }
        }


        /**
         * @function eventListeners
         * @description Assign Event Listeners to the document
         * @return Void
        */
        eventListeners() {

            document.addEventListener('keydown', (evt) => {

                this.value += this.assignValue(evt.key);

                if (evt.key === "Enter" && this.value.endsWith('?')) {

                    try { evt.target.value = ""; } catch(err){ console.log(err);};

                    if (this.value.startsWith("%B") || this.value.startsWith("%E")) {
                        this.creditcard = true;
                        this.setCCValue(this.value);
                        this.value = "";
                    } else {
                        this.creditcard = false;
                        this.value = "";
                    }
                }
            });
        }

        /**
         * @function getCCValue
         * @description extract cc info and validate cc info before populating credit card form
         * @param val String, raw input string from card reader 
         * @return Void
        */
        setCCValue(val) {

            this.resetInputs();
            const input_str = this.getSplitChar(val);
            const items = val.split(input_str);
            const num = this.getCCNumber(items);

            const is_validate = this.validateCardNumber(num);
            this.cc_valid_ele.innerHTML = (is_validate) ? "✓" : "x";

            const card_type = this.getCardType(num);
            this.cc_type_ele.innerHTML = (card_type) ? `${card_type}` : "";

            this.cc_num_input.value = num;
            this.cc_name_input.value = this.getCCName(items);
            this.cc_date_input.value = this.getCCExpDate(val);

        }

        /**
         * @function getCCName
         * @description extract the name from the cc input array 
         * @param items Array[String] split cc input value
         * @return String
        */
        getCCName(items) {
            let name = "";
            if (items.length >= 1) {
                name = items[1];
                if (name.includes("/")) {
                    const firstlast = name.split("/");
                    if (firstlast.length >= 1) {
                        name = `${firstlast[1].trim()} ${firstlast[0].trim()}`;
                    }
                }
            }
            return name;
        }

        /**
         * @function getCCNumber
         * @description get the cc number from the beginning of the input string
         * note the cc input string will start with %B or %E then the cc number 
         * for example: %B4111111111111111^ always ending with a ^ char. 
         * @param items Array[string] cc input value split by  ^ 
         * @return string
         */
        getCCNumber(items) {
            let cc_num = "";
            if (items.length > 0) {
                cc_num = items[0].replace(/[^0-9]/gi, '');
            }
            return cc_num;
        }

        /**
         * @function getCCExpDate
         * @description extract the experation date from the cc input string
         * Note: the exp date is located after the cc number and cc name 
         * %B4111111111111111^LASTNAME/FIRST I ^YYMM <--
         * @param val String, raw input string from card reader 
         * @return string
         * 
        */
        getCCExpDate(val) {

            let exp = "";

            if (val.includes("=")) {
                const items = val.split("=");
                const yymm = items.pop().slice(0, 4);
                const yy = yymm.slice(0, 2);
                const mm = yymm.slice(-2);
                exp = `${mm}/${yy}`;
            }
            return exp;
        }

        /**
         * @function getSplitChar
         * @desction find the correct split by char depending on the cc type 
         * @param val String, raw input string from card reader 
         * @return string
        */
        getSplitChar(val) {

            if (val.includes("%B")) {
                return "^";
            }
            if (val.includes("%E")) {
                return "=";
            }
            return " ";

        }


        /**
         * @function validateCardNumber
         * @description  Check if the number contains only numeric value and is of between 13 to 19 digits
         * @return bool
        */
        validateCardNumber(val) {
            const regex = new RegExp("^[0-9]{13,19}$");
            return (!regex.test(val)) ? false : this.validateLuhnAlgorithm(val);
        }

        /**
         * @function validateLuhnAlgorithm
         * @description  Validate  The Luhn algorithm or Luhn formula, also known as the "modulus 10" or "mod 10" algorithm, 
         * named after its creator, IBM scientist Hans Peter Luhn, is a simple check digit formula used to validate a variety 
         * of identification numbers.
         * @return bool
        */
        validateLuhnAlgorithm(cardNumber) {
            let sum = 0;
            let isEven = false;

            for (let i = cardNumber.length - 1; i >= 0; i--) {
                let digit = parseInt(cardNumber.charAt(i), 10);

                if (isEven) {
                    digit *= 2;
                    if (digit > 9) {
                        digit -= 9;
                    }
                }
                sum += digit;
                isEven = !isEven;
            }

            return sum % 10 === 0;
        }

        /**
         * @function getCardType
         * @description  Return the card type based on the digit input 
         * @return string
        */
        getCardType(cardNumber) {
            const patterns = {
                visa: /^4[0-9]{12}(?:[0-9]{3})?$/,
                mastercard: /^5[1-5][0-9]{14}$/,
                amex: /^3[47][0-9]{13}$/,
                discover: /^6(?:011|5[0-9]{2})[0-9]{12}$/,
            };

            for (const cardType in patterns) {
                if (patterns[cardType].test(cardNumber)) {
                    return cardType;
                }
            }

            return "Other";
        }

        /**
         * @function resetInputs
         * @description Reset all form inputs 
         * @return Void
        */
        resetInputs() {
            this.cc_name_input.value = "";
            this.cc_date_input.value = "";
            this.cc_date_input.value = "";
            this.cc_type_ele.innerHTML = "";
            this.cc_valid_ele.innerHTML = "";
        }
    }

