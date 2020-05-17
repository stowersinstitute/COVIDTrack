(function($) {
    var _timeoutHandler = 0,
        _inputString = '',
        _onKeypress = function(e) {
            if (_timeoutHandler) {
                clearTimeout(_timeoutHandler);
            }

            if(e.key !== "Enter") {
                _inputString += e.key;
            }

            _timeoutHandler = setTimeout(function () {
                if (_inputString.length <= 3) {
                    _inputString = '';
                    return;
                }
                console.log(_inputString);
                $(e.target).trigger('scannerinput', _inputString);
                _inputString = '';

            }, 50); //iPad seems to like 50ms.
        };
    $(document).on({
        keypress: _onKeypress
    });
})($);