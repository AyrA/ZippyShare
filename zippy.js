//Note: This is not part of the plugin, but I use this to quickly validate ZippyShare URLs that make any problem.
//To use, get phantomjs and launch with the parameter format below:

//Parameter format: phantomjs zippy.js <url> [-v]
//Example:          phantomjs zippy.js https://www111.zippyshare.com/v/nfrzPu9C/file.html

var wp = require("webpage");
var args = require("system").args;
var page = wp.create();

//Regular expression to match a regular ZippyShare URL
var zippyreg = /^https?:\/\/www\d+\.zippyshare\.com\/v\/\w+\/file.html$/i;
//The first argument is always the script name, so ">0" is Ok here
var verbose = args.indexOf('-v') > 0;

//error codes
var ERR = {
	//Everything OK
	SUCCESS: 0,
	//Page load error
	FAILED_TO_LOAD_PAGE: 1,
	//Page load timeout
	TIMEOUT: 2,
	//Invalid arguments
	ARGS: 3,
	//Unable to extract real ZippyShare URL
	GET_URL_FAIL: 4
};

//Kill phantom if the website doesn't answers in time
setTimeout(function () {
	phantom.exit(ERR.TIMEOUT);
}, 5000);

//Don't load images. Not necessary because we filter requests anyways but it speeds up page rendering
page.settings.loadImages = false;

//Silence some messages
page.onConsoleMessage = page.onError = function () {};

//Discard all resources that are not html
page.onResourceRequested = function (rd, nr) {
	//quicker method without any logging
	//return rd.id === 1 || nr.abort();
	if (rd.url.match(zippyreg)) {
		if (verbose) {
			console.log("+", rd.id, rd.url);
		}
	} else {
		if (verbose) {
			console.log("-", rd.id, rd.url);
		}
		nr.abort();
	}
};

if (args.length >= 2) {
	var pageStart = Date.now();
	//Get URL argument from argument list
	var url = args.filter(function (v) {
			return !!v.match(zippyreg);
		})[0];
	if (url) {
		if (verbose) {
			console.log("Opening  :", url);
		}
		page.open(url, function (status) {
			if (status !== "success") {
				//This is usually a network error
				phantom.exit(ERR.FAILED_TO_LOAD_PAGE);
			} else {
				var jsStart = Date.now();
				//Getting the full URL is as simple as grabbing the href property of the download button.
				var result = page.evaluate(function () {
						return {
							a: window.a,
							b: window.b,
							url: (document.querySelector("#dlbutton") || {}).href
						};
					});
				if (result.url) {
					if (verbose) {
						//Get the random Id from the URL
						var mod = +result.url.match(/(\d+)\/[^\/]+$/)[1];
						//Perform the same computation that zippyshare does in reverse to test if the formula changed
						var a = Math.round(Math.pow(mod - 3, 1 / 3));
						//Log a few statistics
						console.log("Result   :", result.url);
						console.log("var a    :", result.a);
						console.log("var b    :", result.b);
						console.log("Nonce    :", a);
						//Our computation should be identical with their computation.
						//If not, they changed the formula.
						//This is nothing bad since we only care for the result,
						//but means that some plugins have to be updated.
						console.log("Valid    :", a === result.a ? "Yes" : "No (some plugins need change)");
					} else {
						console.log(result.url);
					}
				} else {
					//Page HTML changed beyond where the page.evaluate works.
					//This means that this script needs adjustment
					if (verbose) {
						console.log("Result   : Fail");
					}
					phantom.exit(ERR.GET_URL_FAIL);
				}
				if (verbose) {
					console.log("JS   (ms):", Date.now() - jsStart);
					console.log("Time (ms):", Date.now() - pageStart);
				}
				phantom.exit(ERR.SUCCESS);
			}
		});
	} else {
		console.log("Argument not a valid ZippyShare URL");
		phantom.exit(ERR.ARGS);
	}
} else {
	//Invalid/no arguments
	console.log(args[0], "<url> [-v]");
	phantom.exit(ERR.ARGS);
}
