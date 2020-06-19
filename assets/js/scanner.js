(function($) {
    var _timeoutHandler = 0,
        _inputString = '',
        _onKeypress = function(e) {
            console.log({key: e.key});
            if (_timeoutHandler) {
                clearTimeout(_timeoutHandler);
            }

            if(e.key !== "Enter") {
                _inputString += e.key;
            } else {
                console.log(_inputString);
                $(e.target).trigger('scannerinput', _inputString);
                _inputString = '';
            }

            // Since the scanner acts like a keyboard we are checking for quickly entered key presses in a short period of time.
            _timeoutHandler = setTimeout(function () {
                // This just checks to make sure we have more than three characters scanned, otherwise we can assume someone is just typing fast.
                if (_inputString.length <= 2) {
                    _inputString = '';
                    return;
                }
            }, 50); //iPad seems to like 50ms.
        };
    $(document).on({
        keypress: _onKeypress
    });
})($);