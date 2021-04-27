// Array.from
if (!Array.from) {
	Array.from = (function() {
		var toStr = Object.prototype.toString;
		var isCallable = function(fn) {
			return typeof fn === 'function' || toStr.call(fn) === '[object Function]';
		};
		var toInteger = function (value) {
			var number = Number(value);
			if (isNaN(number)) { return 0; }
			if (number === 0 || !isFinite(number)) { return number; }
			return (number > 0 ? 1 : -1) * Math.floor(Math.abs(number));
		};
		var maxSafeInteger = Math.pow(2, 53) - 1;
		var toLength = function (value) {
			var len = toInteger(value);
			return Math.min(Math.max(len, 0), maxSafeInteger);
		};

		// Свойство length метода from равно 1.
		return function from(arrayLike/*, mapFn, thisArg */) {
			// 1. Положим C равным значению this.
			var C = this;

			// 2. Положим items равным ToObject(arrayLike).
			var items = Object(arrayLike);

			// 3. ReturnIfAbrupt(items).
			if (arrayLike == null) {
				throw new TypeError('Array.from requires an array-like object - not null or undefined');
			}

			// 4. Если mapfn равен undefined, положим mapping равным false.
			var mapFn = arguments.length > 1 ? arguments[1] : void undefined;
			var T;
			if (typeof mapFn !== 'undefined') {
				// 5. иначе
				// 5. a. Если вызов IsCallable(mapfn) равен false, выкидываем исключение TypeError.
				if (!isCallable(mapFn)) {
					throw new TypeError('Array.from: when provided, the second argument must be a function');
				}

				// 5. b. Если thisArg присутствует, положим T равным thisArg; иначе положим T равным undefined.
				if (arguments.length > 2) {
					T = arguments[2];
				}
			}

			// 10. Положим lenValue равным Get(items, "length").
			// 11. Положим len равным ToLength(lenValue).
			var len = toLength(items.length);

			// 13. Если IsConstructor(C) равен true, то
			// 13. a. Положим A равным результату вызова внутреннего метода [[Construct]]
			//     объекта C со списком аргументов, содержащим единственный элемент len.
			// 14. a. Иначе, положим A равным ArrayCreate(len).
			var A = isCallable(C) ? Object(new C(len)) : new Array(len);

			// 16. Положим k равным 0.
			var k = 0;
			// 17. Пока k < len, будем повторять... (шаги с a по h)
			var kValue;
			while (k < len) {
				kValue = items[k];
				if (mapFn) {
					A[k] = typeof T === 'undefined' ? mapFn(kValue, k) : mapFn.call(T, kValue, k);
				} else {
					A[k] = kValue;
				}
				k += 1;
			}
			// 18. Положим putStatus равным Put(A, "length", len, true).
			A.length = len;
			// 20. Вернём A.
			return A;
		};
	}());
}


// CustomEvent
(function () {
	if ( typeof window.CustomEvent === "function" ) return false;

	function CustomEvent ( event, params ) {
		params = params || { bubbles: false, cancelable: false, detail: null };
		var evt = document.createEvent( 'CustomEvent' );
		evt.initCustomEvent( event, params.bubbles, params.cancelable, params.detail );
		return evt;
	}

	window.CustomEvent = CustomEvent;
})();


// Node.prototype.forEach
if (window.NodeList && !NodeList.prototype.forEach) {
	NodeList.prototype.forEach = Array.prototype.forEach;
}


// Object.assign
if (!Object.assign) {
	Object.defineProperty(Object, 'assign', {
		enumerable: false,
		configurable: true,
		writable: true,
		value: function(target, firstSource) {
			'use strict';
			if (target === undefined || target === null) {
				throw new TypeError('Cannot convert first argument to object');
			}

			var to = Object(target);
			for (var i = 1; i < arguments.length; i++) {
				var nextSource = arguments[i];
				if (nextSource === undefined || nextSource === null) {
					continue;
				}

				var keysArray = Object.keys(Object(nextSource));
				for (var nextIndex = 0, len = keysArray.length; nextIndex < len; nextIndex++) {
					var nextKey = keysArray[nextIndex];
					var desc = Object.getOwnPropertyDescriptor(nextSource, nextKey);
					if (desc !== undefined && desc.enumerable) {
						to[nextKey] = nextSource[nextKey];
					}
				}
			}
			return to;
		}
	});
}


// IntersectionObserver
/**
 * Copyright 2016 Google Inc. All Rights Reserved.
 * Licensed under the W3C SOFTWARE AND DOCUMENT NOTICE AND LICENSE.
 *  https://www.w3.org/Consortium/Legal/2015/copyright-software-and-document
 */
(function() {
	'use strict';

// Exit early if we're not running in a browser.
	if (typeof window !== 'object') {
		return;
	}

// Exit early if all IntersectionObserver and IntersectionObserverEntry
// features are natively supported.
	if ('IntersectionObserver' in window &&
		'IntersectionObserverEntry' in window &&
		'intersectionRatio' in window.IntersectionObserverEntry.prototype) {

		// Minimal polyfill for Edge 15's lack of `isIntersecting`
		// See: https://github.com/w3c/IntersectionObserver/issues/211
		if (!('isIntersecting' in window.IntersectionObserverEntry.prototype)) {
			Object.defineProperty(window.IntersectionObserverEntry.prototype,
				'isIntersecting', {
					get: function () {
						return this.intersectionRatio > 0;
					}
				});
		}
		return;
	}


	/**
	 * A local reference to the document.
	 */
	var document = window.document;


	/**
	 * An IntersectionObserver registry. This registry exists to hold a strong
	 * reference to IntersectionObserver instances currently observing a target
	 * element. Without this registry, instances without another reference may be
	 * garbage collected.
	 */
	var registry = [];


	/**
	 * Creates the global IntersectionObserverEntry constructor.
	 * https://w3c.github.io/IntersectionObserver/#intersection-observer-entry
	 * @param {Object} entry A dictionary of instance properties.
	 * @constructor
	 */
	function IntersectionObserverEntry(entry) {
		this.time = entry.time;
		this.target = entry.target;
		this.rootBounds = entry.rootBounds;
		this.boundingClientRect = entry.boundingClientRect;
		this.intersectionRect = entry.intersectionRect || getEmptyRect();
		this.isIntersecting = !!entry.intersectionRect;

		// Calculates the intersection ratio.
		var targetRect = this.boundingClientRect;
		var targetArea = targetRect.width * targetRect.height;
		var intersectionRect = this.intersectionRect;
		var intersectionArea = intersectionRect.width * intersectionRect.height;

		// Sets intersection ratio.
		if (targetArea) {
			// Round the intersection ratio to avoid floating point math issues:
			// https://github.com/w3c/IntersectionObserver/issues/324
			this.intersectionRatio = Number((intersectionArea / targetArea).toFixed(4));
		} else {
			// If area is zero and is intersecting, sets to 1, otherwise to 0
			this.intersectionRatio = this.isIntersecting ? 1 : 0;
		}
	}


	/**
	 * Creates the global IntersectionObserver constructor.
	 * https://w3c.github.io/IntersectionObserver/#intersection-observer-interface
	 * @param {Function} callback The function to be invoked after intersection
	 *     changes have queued. The function is not invoked if the queue has
	 *     been emptied by calling the `takeRecords` method.
	 * @param {Object=} opt_options Optional configuration options.
	 * @constructor
	 */
	function IntersectionObserver(callback, opt_options) {

		var options = opt_options || {};

		if (typeof callback != 'function') {
			throw new Error('callback must be a function');
		}

		if (options.root && options.root.nodeType != 1) {
			throw new Error('root must be an Element');
		}

		// Binds and throttles `this._checkForIntersections`.
		this._checkForIntersections = throttle(
			this._checkForIntersections.bind(this), this.THROTTLE_TIMEOUT);

		// Private properties.
		this._callback = callback;
		this._observationTargets = [];
		this._queuedEntries = [];
		this._rootMarginValues = this._parseRootMargin(options.rootMargin);

		// Public properties.
		this.thresholds = this._initThresholds(options.threshold);
		this.root = options.root || null;
		this.rootMargin = this._rootMarginValues.map(function(margin) {
			return margin.value + margin.unit;
		}).join(' ');
	}


	/**
	 * The minimum interval within which the document will be checked for
	 * intersection changes.
	 */
	IntersectionObserver.prototype.THROTTLE_TIMEOUT = 100;


	/**
	 * The frequency in which the polyfill polls for intersection changes.
	 * this can be updated on a per instance basis and must be set prior to
	 * calling `observe` on the first target.
	 */
	IntersectionObserver.prototype.POLL_INTERVAL = null;

	/**
	 * Use a mutation observer on the root element
	 * to detect intersection changes.
	 */
	IntersectionObserver.prototype.USE_MUTATION_OBSERVER = true;


	/**
	 * Starts observing a target element for intersection changes based on
	 * the thresholds values.
	 * @param {Element} target The DOM element to observe.
	 */
	IntersectionObserver.prototype.observe = function(target) {
		var isTargetAlreadyObserved = this._observationTargets.some(function(item) {
			return item.element == target;
		});

		if (isTargetAlreadyObserved) {
			return;
		}

		if (!(target && target.nodeType == 1)) {
			throw new Error('target must be an Element');
		}

		this._registerInstance();
		this._observationTargets.push({element: target, entry: null});
		this._monitorIntersections();
		this._checkForIntersections();
	};


	/**
	 * Stops observing a target element for intersection changes.
	 * @param {Element} target The DOM element to observe.
	 */
	IntersectionObserver.prototype.unobserve = function(target) {
		this._observationTargets =
			this._observationTargets.filter(function(item) {

				return item.element != target;
			});
		if (!this._observationTargets.length) {
			this._unmonitorIntersections();
			this._unregisterInstance();
		}
	};


	/**
	 * Stops observing all target elements for intersection changes.
	 */
	IntersectionObserver.prototype.disconnect = function() {
		this._observationTargets = [];
		this._unmonitorIntersections();
		this._unregisterInstance();
	};


	/**
	 * Returns any queue entries that have not yet been reported to the
	 * callback and clears the queue. This can be used in conjunction with the
	 * callback to obtain the absolute most up-to-date intersection information.
	 * @return {Array} The currently queued entries.
	 */
	IntersectionObserver.prototype.takeRecords = function() {
		var records = this._queuedEntries.slice();
		this._queuedEntries = [];
		return records;
	};


	/**
	 * Accepts the threshold value from the user configuration object and
	 * returns a sorted array of unique threshold values. If a value is not
	 * between 0 and 1 and error is thrown.
	 * @private
	 * @param {Array|number=} opt_threshold An optional threshold value or
	 *     a list of threshold values, defaulting to [0].
	 * @return {Array} A sorted list of unique and valid threshold values.
	 */
	IntersectionObserver.prototype._initThresholds = function(opt_threshold) {
		var threshold = opt_threshold || [0];
		if (!Array.isArray(threshold)) threshold = [threshold];

		return threshold.sort().filter(function(t, i, a) {
			if (typeof t != 'number' || isNaN(t) || t < 0 || t > 1) {
				throw new Error('threshold must be a number between 0 and 1 inclusively');
			}
			return t !== a[i - 1];
		});
	};


	/**
	 * Accepts the rootMargin value from the user configuration object
	 * and returns an array of the four margin values as an object containing
	 * the value and unit properties. If any of the values are not properly
	 * formatted or use a unit other than px or %, and error is thrown.
	 * @private
	 * @param {string=} opt_rootMargin An optional rootMargin value,
	 *     defaulting to '0px'.
	 * @return {Array<Object>} An array of margin objects with the keys
	 *     value and unit.
	 */
	IntersectionObserver.prototype._parseRootMargin = function(opt_rootMargin) {
		var marginString = opt_rootMargin || '0px';
		var margins = marginString.split(/\s+/).map(function(margin) {
			var parts = /^(-?\d*\.?\d+)(px|%)$/.exec(margin);
			if (!parts) {
				throw new Error('rootMargin must be specified in pixels or percent');
			}
			return {value: parseFloat(parts[1]), unit: parts[2]};
		});

		// Handles shorthand.
		margins[1] = margins[1] || margins[0];
		margins[2] = margins[2] || margins[0];
		margins[3] = margins[3] || margins[1];

		return margins;
	};


	/**
	 * Starts polling for intersection changes if the polling is not already
	 * happening, and if the page's visibility state is visible.
	 * @private
	 */
	IntersectionObserver.prototype._monitorIntersections = function() {
		if (!this._monitoringIntersections) {
			this._monitoringIntersections = true;

			// If a poll interval is set, use polling instead of listening to
			// resize and scroll events or DOM mutations.
			if (this.POLL_INTERVAL) {
				this._monitoringInterval = setInterval(
					this._checkForIntersections, this.POLL_INTERVAL);
			}
			else {
				addEvent(window, 'resize', this._checkForIntersections, true);
				addEvent(document, 'scroll', this._checkForIntersections, true);

				if (this.USE_MUTATION_OBSERVER && 'MutationObserver' in window) {
					this._domObserver = new MutationObserver(this._checkForIntersections);
					this._domObserver.observe(document, {
						attributes: true,
						childList: true,
						characterData: true,
						subtree: true
					});
				}
			}
		}
	};


	/**
	 * Stops polling for intersection changes.
	 * @private
	 */
	IntersectionObserver.prototype._unmonitorIntersections = function() {
		if (this._monitoringIntersections) {
			this._monitoringIntersections = false;

			clearInterval(this._monitoringInterval);
			this._monitoringInterval = null;

			removeEvent(window, 'resize', this._checkForIntersections, true);
			removeEvent(document, 'scroll', this._checkForIntersections, true);

			if (this._domObserver) {
				this._domObserver.disconnect();
				this._domObserver = null;
			}
		}
	};


	/**
	 * Scans each observation target for intersection changes and adds them
	 * to the internal entries queue. If new entries are found, it
	 * schedules the callback to be invoked.
	 * @private
	 */
	IntersectionObserver.prototype._checkForIntersections = function() {
		var rootIsInDom = this._rootIsInDom();
		var rootRect = rootIsInDom ? this._getRootRect() : getEmptyRect();

		this._observationTargets.forEach(function(item) {
			var target = item.element;
			var targetRect = getBoundingClientRect(target);
			var rootContainsTarget = this._rootContainsTarget(target);
			var oldEntry = item.entry;
			var intersectionRect = rootIsInDom && rootContainsTarget &&
				this._computeTargetAndRootIntersection(target, rootRect);

			var newEntry = item.entry = new IntersectionObserverEntry({
				time: now(),
				target: target,
				boundingClientRect: targetRect,
				rootBounds: rootRect,
				intersectionRect: intersectionRect
			});

			if (!oldEntry) {
				this._queuedEntries.push(newEntry);
			} else if (rootIsInDom && rootContainsTarget) {
				// If the new entry intersection ratio has crossed any of the
				// thresholds, add a new entry.
				if (this._hasCrossedThreshold(oldEntry, newEntry)) {
					this._queuedEntries.push(newEntry);
				}
			} else {
				// If the root is not in the DOM or target is not contained within
				// root but the previous entry for this target had an intersection,
				// add a new record indicating removal.
				if (oldEntry && oldEntry.isIntersecting) {
					this._queuedEntries.push(newEntry);
				}
			}
		}, this);

		if (this._queuedEntries.length) {
			this._callback(this.takeRecords(), this);
		}
	};


	/**
	 * Accepts a target and root rect computes the intersection between then
	 * following the algorithm in the spec.
	 * TODO(philipwalton): at this time clip-path is not considered.
	 * https://w3c.github.io/IntersectionObserver/#calculate-intersection-rect-algo
	 * @param {Element} target The target DOM element
	 * @param {Object} rootRect The bounding rect of the root after being
	 *     expanded by the rootMargin value.
	 * @return {?Object} The final intersection rect object or undefined if no
	 *     intersection is found.
	 * @private
	 */
	IntersectionObserver.prototype._computeTargetAndRootIntersection =
		function(target, rootRect) {

			// If the element isn't displayed, an intersection can't happen.
			if (window.getComputedStyle(target).display == 'none') return;

			var targetRect = getBoundingClientRect(target);
			var intersectionRect = targetRect;
			var parent = getParentNode(target);
			var atRoot = false;

			while (!atRoot) {
				var parentRect = null;
				var parentComputedStyle = parent.nodeType == 1 ?
					window.getComputedStyle(parent) : {};

				// If the parent isn't displayed, an intersection can't happen.
				if (parentComputedStyle.display == 'none') return;

				if (parent == this.root || parent == document) {
					atRoot = true;
					parentRect = rootRect;
				} else {
					// If the element has a non-visible overflow, and it's not the <body>
					// or <html> element, update the intersection rect.
					// Note: <body> and <html> cannot be clipped to a rect that's not also
					// the document rect, so no need to compute a new intersection.
					if (parent != document.body &&
						parent != document.documentElement &&
						parentComputedStyle.overflow != 'visible') {
						parentRect = getBoundingClientRect(parent);
					}
				}

				// If either of the above conditionals set a new parentRect,
				// calculate new intersection data.
				if (parentRect) {
					intersectionRect = computeRectIntersection(parentRect, intersectionRect);

					if (!intersectionRect) break;
				}
				parent = getParentNode(parent);
			}
			return intersectionRect;
		};


	/**
	 * Returns the root rect after being expanded by the rootMargin value.
	 * @return {Object} The expanded root rect.
	 * @private
	 */
	IntersectionObserver.prototype._getRootRect = function() {
		var rootRect;
		if (this.root) {
			rootRect = getBoundingClientRect(this.root);
		} else {
			// Use <html>/<body> instead of window since scroll bars affect size.
			var html = document.documentElement;
			var body = document.body;
			rootRect = {
				top: 0,
				left: 0,
				right: html.clientWidth || body.clientWidth,
				width: html.clientWidth || body.clientWidth,
				bottom: html.clientHeight || body.clientHeight,
				height: html.clientHeight || body.clientHeight
			};
		}
		return this._expandRectByRootMargin(rootRect);
	};


	/**
	 * Accepts a rect and expands it by the rootMargin value.
	 * @param {Object} rect The rect object to expand.
	 * @return {Object} The expanded rect.
	 * @private
	 */
	IntersectionObserver.prototype._expandRectByRootMargin = function(rect) {
		var margins = this._rootMarginValues.map(function(margin, i) {
			return margin.unit == 'px' ? margin.value :
				margin.value * (i % 2 ? rect.width : rect.height) / 100;
		});
		var newRect = {
			top: rect.top - margins[0],
			right: rect.right + margins[1],
			bottom: rect.bottom + margins[2],
			left: rect.left - margins[3]
		};
		newRect.width = newRect.right - newRect.left;
		newRect.height = newRect.bottom - newRect.top;

		return newRect;
	};


	/**
	 * Accepts an old and new entry and returns true if at least one of the
	 * threshold values has been crossed.
	 * @param {?IntersectionObserverEntry} oldEntry The previous entry for a
	 *    particular target element or null if no previous entry exists.
	 * @param {IntersectionObserverEntry} newEntry The current entry for a
	 *    particular target element.
	 * @return {boolean} Returns true if a any threshold has been crossed.
	 * @private
	 */
	IntersectionObserver.prototype._hasCrossedThreshold =
		function(oldEntry, newEntry) {

			// To make comparing easier, an entry that has a ratio of 0
			// but does not actually intersect is given a value of -1
			var oldRatio = oldEntry && oldEntry.isIntersecting ?
				oldEntry.intersectionRatio || 0 : -1;
			var newRatio = newEntry.isIntersecting ?
				newEntry.intersectionRatio || 0 : -1;

			// Ignore unchanged ratios
			if (oldRatio === newRatio) return;

			for (var i = 0; i < this.thresholds.length; i++) {
				var threshold = this.thresholds[i];

				// Return true if an entry matches a threshold or if the new ratio
				// and the old ratio are on the opposite sides of a threshold.
				if (threshold == oldRatio || threshold == newRatio ||
					threshold < oldRatio !== threshold < newRatio) {
					return true;
				}
			}
		};


	/**
	 * Returns whether or not the root element is an element and is in the DOM.
	 * @return {boolean} True if the root element is an element and is in the DOM.
	 * @private
	 */
	IntersectionObserver.prototype._rootIsInDom = function() {
		return !this.root || containsDeep(document, this.root);
	};


	/**
	 * Returns whether or not the target element is a child of root.
	 * @param {Element} target The target element to check.
	 * @return {boolean} True if the target element is a child of root.
	 * @private
	 */
	IntersectionObserver.prototype._rootContainsTarget = function(target) {
		return containsDeep(this.root || document, target);
	};


	/**
	 * Adds the instance to the global IntersectionObserver registry if it isn't
	 * already present.
	 * @private
	 */
	IntersectionObserver.prototype._registerInstance = function() {
		if (registry.indexOf(this) < 0) {
			registry.push(this);
		}
	};


	/**
	 * Removes the instance from the global IntersectionObserver registry.
	 * @private
	 */
	IntersectionObserver.prototype._unregisterInstance = function() {
		var index = registry.indexOf(this);
		if (index != -1) registry.splice(index, 1);
	};


	/**
	 * Returns the result of the performance.now() method or null in browsers
	 * that don't support the API.
	 * @return {number} The elapsed time since the page was requested.
	 */
	function now() {
		return window.performance && performance.now && performance.now();
	}


	/**
	 * Throttles a function and delays its execution, so it's only called at most
	 * once within a given time period.
	 * @param {Function} fn The function to throttle.
	 * @param {number} timeout The amount of time that must pass before the
	 *     function can be called again.
	 * @return {Function} The throttled function.
	 */
	function throttle(fn, timeout) {
		var timer = null;
		return function () {
			if (!timer) {
				timer = setTimeout(function() {
					fn();
					timer = null;
				}, timeout);
			}
		};
	}


	/**
	 * Adds an event handler to a DOM node ensuring cross-browser compatibility.
	 * @param {Node} node The DOM node to add the event handler to.
	 * @param {string} event The event name.
	 * @param {Function} fn The event handler to add.
	 * @param {boolean} opt_useCapture Optionally adds the even to the capture
	 *     phase. Note: this only works in modern browsers.
	 */
	function addEvent(node, event, fn, opt_useCapture) {
		if (typeof node.addEventListener == 'function') {
			node.addEventListener(event, fn, opt_useCapture || false);
		}
		else if (typeof node.attachEvent == 'function') {
			node.attachEvent('on' + event, fn);
		}
	}


	/**
	 * Removes a previously added event handler from a DOM node.
	 * @param {Node} node The DOM node to remove the event handler from.
	 * @param {string} event The event name.
	 * @param {Function} fn The event handler to remove.
	 * @param {boolean} opt_useCapture If the event handler was added with this
	 *     flag set to true, it should be set to true here in order to remove it.
	 */
	function removeEvent(node, event, fn, opt_useCapture) {
		if (typeof node.removeEventListener == 'function') {
			node.removeEventListener(event, fn, opt_useCapture || false);
		}
		else if (typeof node.detatchEvent == 'function') {
			node.detatchEvent('on' + event, fn);
		}
	}


	/**
	 * Returns the intersection between two rect objects.
	 * @param {Object} rect1 The first rect.
	 * @param {Object} rect2 The second rect.
	 * @return {?Object} The intersection rect or undefined if no intersection
	 *     is found.
	 */
	function computeRectIntersection(rect1, rect2) {
		var top = Math.max(rect1.top, rect2.top);
		var bottom = Math.min(rect1.bottom, rect2.bottom);
		var left = Math.max(rect1.left, rect2.left);
		var right = Math.min(rect1.right, rect2.right);
		var width = right - left;
		var height = bottom - top;

		return (width >= 0 && height >= 0) && {
			top: top,
			bottom: bottom,
			left: left,
			right: right,
			width: width,
			height: height
		};
	}


	/**
	 * Shims the native getBoundingClientRect for compatibility with older IE.
	 * @param {Element} el The element whose bounding rect to get.
	 * @return {Object} The (possibly shimmed) rect of the element.
	 */
	function getBoundingClientRect(el) {
		var rect;

		try {
			rect = el.getBoundingClientRect();
		} catch (err) {
			// Ignore Windows 7 IE11 "Unspecified error"
			// https://github.com/w3c/IntersectionObserver/pull/205
		}

		if (!rect) return getEmptyRect();

		// Older IE
		if (!(rect.width && rect.height)) {
			rect = {
				top: rect.top,
				right: rect.right,
				bottom: rect.bottom,
				left: rect.left,
				width: rect.right - rect.left,
				height: rect.bottom - rect.top
			};
		}
		return rect;
	}


	/**
	 * Returns an empty rect object. An empty rect is returned when an element
	 * is not in the DOM.
	 * @return {Object} The empty rect.
	 */
	function getEmptyRect() {
		return {
			top: 0,
			bottom: 0,
			left: 0,
			right: 0,
			width: 0,
			height: 0
		};
	}

	/**
	 * Checks to see if a parent element contains a child element (including inside
	 * shadow DOM).
	 * @param {Node} parent The parent element.
	 * @param {Node} child The child element.
	 * @return {boolean} True if the parent node contains the child node.
	 */
	function containsDeep(parent, child) {
		var node = child;
		while (node) {
			if (node == parent) return true;

			node = getParentNode(node);
		}
		return false;
	}


	/**
	 * Gets the parent node of an element or its host element if the parent node
	 * is a shadow root.
	 * @param {Node} node The node whose parent to get.
	 * @return {Node|null} The parent node or null if no parent exists.
	 */
	function getParentNode(node) {
		var parent = node.parentNode;

		if (parent && parent.nodeType == 11 && parent.host) {
			// If the parent is a shadow root, return the host element.
			return parent.host;
		}

		if (parent && parent.assignedSlot) {
			// If the parent is distributed in a <slot>, return the parent of a slot.
			return parent.assignedSlot.parentNode;
		}

		return parent;
	}


// Exposes the constructors globally.
	window.IntersectionObserver = IntersectionObserver;
	window.IntersectionObserverEntry = IntersectionObserverEntry;

}());


/**
 * @module Promise polyfill
 */

(function (root) {
	// Store setTimeout reference so promise-polyfill will be unaffected by
	// other code modifying setTimeout (like sinon.useFakeTimers())
	var setTimeoutFunc = setTimeout;

	function noop() {
	}

	// Polyfill for Function.prototype.bind
	function bind(fn, thisArg) {
		return function () {
			fn.apply(thisArg, arguments);
		};
	}

	function Promise(fn) {
		if (typeof this !== 'object') throw new TypeError('Promises must be constructed via new');
		if (typeof fn !== 'function') throw new TypeError('not a function');
		this._state = 0;
		this._handled = false;
		this._value = undefined;
		this._deferreds = [];

		doResolve(fn, this);
	}

	function handle(self, deferred) {
		while (self._state === 3) {
			self = self._value;
		}
		if (self._state === 0) {
			self._deferreds.push(deferred);
			return;
		}
		self._handled = true;
		Promise._immediateFn(function () {
			var cb = self._state === 1 ? deferred.onFulfilled : deferred.onRejected;
			if (cb === null) {
				(self._state === 1 ? resolve : reject)(deferred.promise, self._value);
				return;
			}
			var ret;
			try {
				ret = cb(self._value);
			} catch (e) {
				reject(deferred.promise, e);
				return;
			}
			resolve(deferred.promise, ret);
		});
	}

	function resolve(self, newValue) {
		try {
			// Promise Resolution Procedure: https://github.com/promises-aplus/promises-spec#the-promise-resolution-procedure
			if (newValue === self) throw new TypeError('A promise cannot be resolved with itself.');
			if (newValue && (typeof newValue === 'object' || typeof newValue === 'function')) {
				var then = newValue.then;
				if (newValue instanceof Promise) {
					self._state = 3;
					self._value = newValue;
					finale(self);
					return;
				} else if (typeof then === 'function') {
					doResolve(bind(then, newValue), self);
					return;
				}
			}
			self._state = 1;
			self._value = newValue;
			finale(self);
		} catch (e) {
			reject(self, e);
		}
	}

	function reject(self, newValue) {
		self._state = 2;
		self._value = newValue;
		finale(self);
	}

	function finale(self) {
		if (self._state === 2 && self._deferreds.length === 0) {
			Promise._immediateFn(function () {
				if (!self._handled) {
					Promise._unhandledRejectionFn(self._value);
				}
			});
		}

		for (var i = 0, len = self._deferreds.length; i < len; i++) {
			handle(self, self._deferreds[i]);
		}
		self._deferreds = null;
	}

	function Handler(onFulfilled, onRejected, promise) {
		this.onFulfilled = typeof onFulfilled === 'function' ? onFulfilled : null;
		this.onRejected = typeof onRejected === 'function' ? onRejected : null;
		this.promise = promise;
	}

	/**
	 * Take a potentially misbehaving resolver function and make sure
	 * onFulfilled and onRejected are only called once.
	 *
	 * Makes no guarantees about asynchrony.
	 */
	function doResolve(fn, self) {
		var done = false;
		try {
			fn(function (value) {
				if (done) return;
				done = true;
				resolve(self, value);
			}, function (reason) {
				if (done) return;
				done = true;
				reject(self, reason);
			});
		} catch (ex) {
			if (done) return;
			done = true;
			reject(self, ex);
		}
	}

	Promise.prototype['catch'] = function (onRejected) {
		return this.then(null, onRejected);
	};

	Promise.prototype.then = function (onFulfilled, onRejected) {
		var prom = new (this.constructor)(noop);

		handle(this, new Handler(onFulfilled, onRejected, prom));
		return prom;
	};

	Promise.all = function (arr) {
		var args = Array.prototype.slice.call(arr);

		return new Promise(function (resolve, reject) {
			if (args.length === 0) return resolve([]);
			var remaining = args.length;

			function res(i, val) {
				try {
					if (val && (typeof val === 'object' || typeof val === 'function')) {
						var then = val.then;
						if (typeof then === 'function') {
							then.call(val, function (val) {
								res(i, val);
							}, reject);
							return;
						}
					}
					args[i] = val;
					if (--remaining === 0) {
						resolve(args);
					}
				} catch (ex) {
					reject(ex);
				}
			}

			for (var i = 0; i < args.length; i++) {
				res(i, args[i]);
			}
		});
	};

	Promise.resolve = function (value) {
		if (value && typeof value === 'object' && value.constructor === Promise) {
			return value;
		}

		return new Promise(function (resolve) {
			resolve(value);
		});
	};

	Promise.reject = function (value) {
		return new Promise(function (resolve, reject) {
			reject(value);
		});
	};

	Promise.race = function (values) {
		return new Promise(function (resolve, reject) {
			for (var i = 0, len = values.length; i < len; i++) {
				values[i].then(resolve, reject);
			}
		});
	};

	// Use polyfill for setImmediate for performance gains
	Promise._immediateFn = (typeof setImmediate === 'function' && function (fn) {
			setImmediate(fn);
		}) ||
		function (fn) {
			setTimeoutFunc(fn, 0);
		};

	Promise._unhandledRejectionFn = function _unhandledRejectionFn(err) {
		if (typeof console !== 'undefined' && console) {
			console.warn('Possible Unhandled Promise Rejection:', err); // eslint-disable-line no-console
		}
	};

	/**
	 * Set the immediate function to execute callbacks
	 * @param fn {function} Function to execute
	 * @deprecated
	 */
	Promise._setImmediateFn = function _setImmediateFn(fn) {
		Promise._immediateFn = fn;
	};

	/**
	 * Change the function to execute on unhandled rejection
	 * @param {function} fn Function to execute on unhandled rejection
	 * @deprecated
	 */
	Promise._setUnhandledRejectionFn = function _setUnhandledRejectionFn(fn) {
		Promise._unhandledRejectionFn = fn;
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = Promise;
	} else if (root !== undefined && !root.Promise) {
		root.Promise = Promise;
	}

	// Add to window
	if (!window.Promise) {
		window.Promise = Promise;
	}

})(this);
