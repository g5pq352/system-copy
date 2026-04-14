$.validator.addMethod("alphanumeric", function(value, element) {
    return this.optional(element) || /^[a-zA-Z0-9_]+$/.test(value);
    // return this.optional(element) || /^[a-zA-Z][a-zA-Z0-9_]*$/.test(value);
}, "請輸入英文數字底線");

$.validator.addMethod("alphanumeriUnderscoreCminus", function(value, element) {
    return this.optional(element) || /^[a-zA-Z0-9_-]+$/.test(value);
    // return this.optional(element) || /^[a-zA-Z][a-zA-Z0-9_]*$/.test(value);
}, "請輸入英文數字底線減號");

$.validator.addMethod("atLeastOneLang", function(value, element) {
    return $(".lang-check:checked").length > 0;
}, "請至少選擇一個語系");