const node_env = globalThis.process?.env?.NODE_ENV;
var DEV = node_env && !node_env.toLowerCase().startsWith('prod');

// Store the references to globals in case someone tries to monkey patch these, causing the below
// to de-opt (this occurs often when using popular extensions).
var is_array = Array.isArray;
var index_of = Array.prototype.indexOf;
var includes = Array.prototype.includes;
var array_from = Array.from;
var define_property = Object.defineProperty;
var get_descriptor = Object.getOwnPropertyDescriptor;
var get_descriptors = Object.getOwnPropertyDescriptors;
var object_prototype = Object.prototype;
var array_prototype = Array.prototype;
var get_prototype_of = Object.getPrototypeOf;
var is_extensible = Object.isExtensible;

/**
 * @param {any} thing
 * @returns {thing is Function}
 */
function is_function(thing) {
	return typeof thing === 'function';
}

const noop = () => {};

// Adapted from https://github.com/then/is-promise/blob/master/index.js
// Distributed under MIT License https://github.com/then/is-promise/blob/master/LICENSE

/**
 * @template [T=any]
 * @param {any} value
 * @returns {value is PromiseLike<T>}
 */
function is_promise(value) {
	return typeof value?.then === 'function';
}

/** @param {Function} fn */
function run(fn) {
	return fn();
}

/** @param {Array<() => void>} arr */
function run_all(arr) {
	for (var i = 0; i < arr.length; i++) {
		arr[i]();
	}
}

/**
 * TODO replace with Promise.withResolvers once supported widely enough
 * @template [T=void]
 */
function deferred() {
	/** @type {(value: T) => void} */
	var resolve;

	/** @type {(reason: any) => void} */
	var reject;

	/** @type {Promise<T>} */
	var promise = new Promise((res, rej) => {
		resolve = res;
		reject = rej;
	});

	// @ts-expect-error
	return { promise, resolve, reject };
}

/**
 * When encountering a situation like `let [a, b, c] = $derived(blah())`,
 * we need to stash an intermediate value that `a`, `b`, and `c` derive
 * from, in case it's an iterable
 * @template T
 * @param {ArrayLike<T> | Iterable<T>} value
 * @param {number} [n]
 * @returns {Array<T>}
 */
function to_array(value, n) {
	// return arrays unchanged
	if (Array.isArray(value)) {
		return value;
	}

	// if value is not iterable, or `n` is unspecified (indicates a rest
	// element, which means we're not concerned about unbounded iterables)
	// convert to an array with `Array.from`
	if (n === undefined || !(Symbol.iterator in value)) {
		return Array.from(value);
	}

	// otherwise, populate an array with `n` values

	/** @type {T[]} */
	const array = [];

	for (const element of value) {
		array.push(element);
		if (array.length === n) break;
	}

	return array;
}

// General flags
const DERIVED = 1 << 1;
const EFFECT = 1 << 2;
const RENDER_EFFECT = 1 << 3;
/**
 * An effect that does not destroy its child effects when it reruns.
 * Runs as part of render effects, i.e. not eagerly as part of tree traversal or effect flushing.
 */
const MANAGED_EFFECT = 1 << 24;
/**
 * An effect that does not destroy its child effects when it reruns (like MANAGED_EFFECT).
 * Runs eagerly as part of tree traversal or effect flushing.
 */
const BLOCK_EFFECT = 1 << 4;
const BRANCH_EFFECT = 1 << 5;
const ROOT_EFFECT = 1 << 6;
const BOUNDARY_EFFECT = 1 << 7;
/**
 * Indicates that a reaction is connected to an effect root — either it is an effect,
 * or it is a derived that is depended on by at least one effect. If a derived has
 * no dependents, we can disconnect it from the graph, allowing it to either be
 * GC'd or reconnected later if an effect comes to depend on it again
 */
const CONNECTED = 1 << 9;
const CLEAN = 1 << 10;
const DIRTY = 1 << 11;
const MAYBE_DIRTY = 1 << 12;
const INERT = 1 << 13;
const DESTROYED = 1 << 14;
/** Set once a reaction has run for the first time */
const REACTION_RAN = 1 << 15;
/** Effect is in the process of getting destroyed. Can be observed in child teardown functions */
const DESTROYING = 1 << 25;

// Flags exclusive to effects
/**
 * 'Transparent' effects do not create a transition boundary.
 * This is on a block effect 99% of the time but may also be on a branch effect if its parent block effect was pruned
 */
const EFFECT_TRANSPARENT = 1 << 16;
const EAGER_EFFECT = 1 << 17;
const HEAD_EFFECT = 1 << 18;
const EFFECT_PRESERVED = 1 << 19;
const USER_EFFECT = 1 << 20;
const EFFECT_OFFSCREEN = 1 << 25;

// Flags exclusive to deriveds
/**
 * Tells that we marked this derived and its reactions as visited during the "mark as (maybe) dirty"-phase.
 * Will be lifted during execution of the derived and during checking its dirty state (both are necessary
 * because a derived might be checked but not executed).
 */
const WAS_MARKED = 1 << 16;

// Flags used for async
const REACTION_IS_UPDATING = 1 << 21;
const ASYNC = 1 << 22;

const ERROR_VALUE = 1 << 23;

const STATE_SYMBOL = Symbol('$state');
const LEGACY_PROPS = Symbol('legacy props');
const LOADING_ATTR_SYMBOL = Symbol('');
const PROXY_PATH_SYMBOL = Symbol('proxy path');

/** allow users to ignore aborted signal errors if `reason.name === 'StaleReactionError` */
const STALE_REACTION = new (class StaleReactionError extends Error {
	name = 'StaleReactionError';
	message = 'The reaction that called `getAbortSignal()` was re-run or destroyed';
})();
const ELEMENT_NODE = 1;
const DOCUMENT_FRAGMENT_NODE = 11;

/* This file is generated by scripts/process-messages/index.js. Do not edit! */


/**
 * A snippet function was passed invalid arguments. Snippets should only be instantiated via `{@render ...}`
 * @returns {never}
 */
function invalid_snippet_arguments() {
	if (DEV) {
		const error = new Error(`invalid_snippet_arguments\nA snippet function was passed invalid arguments. Snippets should only be instantiated via \`{@render ...}\`\nhttps://svelte.dev/e/invalid_snippet_arguments`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/invalid_snippet_arguments`);
	}
}

/**
 * An invariant violation occurred, meaning Svelte's internal assumptions were flawed. This is a bug in Svelte, not your app — please open an issue at https://github.com/sveltejs/svelte, citing the following message: "%message%"
 * @param {string} message
 * @returns {never}
 */
function invariant_violation(message) {
	if (DEV) {
		const error = new Error(`invariant_violation\nAn invariant violation occurred, meaning Svelte's internal assumptions were flawed. This is a bug in Svelte, not your app — please open an issue at https://github.com/sveltejs/svelte, citing the following message: "${message}"\nhttps://svelte.dev/e/invariant_violation`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/invariant_violation`);
	}
}

/**
 * `%name%(...)` can only be used during component initialisation
 * @param {string} name
 * @returns {never}
 */
function lifecycle_outside_component(name) {
	if (DEV) {
		const error = new Error(`lifecycle_outside_component\n\`${name}(...)\` can only be used during component initialisation\nhttps://svelte.dev/e/lifecycle_outside_component`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/lifecycle_outside_component`);
	}
}

/**
 * Attempted to render a snippet without a `{@render}` block. This would cause the snippet code to be stringified instead of its content being rendered to the DOM. To fix this, change `{snippet}` to `{@render snippet()}`.
 * @returns {never}
 */
function snippet_without_render_tag() {
	if (DEV) {
		const error = new Error(`snippet_without_render_tag\nAttempted to render a snippet without a \`{@render}\` block. This would cause the snippet code to be stringified instead of its content being rendered to the DOM. To fix this, change \`{snippet}\` to \`{@render snippet()}\`.\nhttps://svelte.dev/e/snippet_without_render_tag`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/snippet_without_render_tag`);
	}
}

/**
 * `%name%` is not a store with a `subscribe` method
 * @param {string} name
 * @returns {never}
 */
function store_invalid_shape(name) {
	if (DEV) {
		const error = new Error(`store_invalid_shape\n\`${name}\` is not a store with a \`subscribe\` method\nhttps://svelte.dev/e/store_invalid_shape`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/store_invalid_shape`);
	}
}

/* This file is generated by scripts/process-messages/index.js. Do not edit! */


/**
 * Cannot create a `$derived(...)` with an `await` expression outside of an effect tree
 * @returns {never}
 */
function async_derived_orphan() {
	if (DEV) {
		const error = new Error(`async_derived_orphan\nCannot create a \`$derived(...)\` with an \`await\` expression outside of an effect tree\nhttps://svelte.dev/e/async_derived_orphan`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/async_derived_orphan`);
	}
}

/**
 * Using `bind:value` together with a checkbox input is not allowed. Use `bind:checked` instead
 * @returns {never}
 */
function bind_invalid_checkbox_value() {
	if (DEV) {
		const error = new Error(`bind_invalid_checkbox_value\nUsing \`bind:value\` together with a checkbox input is not allowed. Use \`bind:checked\` instead\nhttps://svelte.dev/e/bind_invalid_checkbox_value`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/bind_invalid_checkbox_value`);
	}
}

/**
 * Calling `%method%` on a component instance (of %component%) is no longer valid in Svelte 5
 * @param {string} method
 * @param {string} component
 * @returns {never}
 */
function component_api_changed(method, component) {
	if (DEV) {
		const error = new Error(`component_api_changed\nCalling \`${method}\` on a component instance (of ${component}) is no longer valid in Svelte 5\nhttps://svelte.dev/e/component_api_changed`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/component_api_changed`);
	}
}

/**
 * Attempted to instantiate %component% with `new %name%`, which is no longer valid in Svelte 5. If this component is not under your control, set the `compatibility.componentApi` compiler option to `4` to keep it working.
 * @param {string} component
 * @param {string} name
 * @returns {never}
 */
function component_api_invalid_new(component, name) {
	if (DEV) {
		const error = new Error(`component_api_invalid_new\nAttempted to instantiate ${component} with \`new ${name}\`, which is no longer valid in Svelte 5. If this component is not under your control, set the \`compatibility.componentApi\` compiler option to \`4\` to keep it working.\nhttps://svelte.dev/e/component_api_invalid_new`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/component_api_invalid_new`);
	}
}

/**
 * A derived value cannot reference itself recursively
 * @returns {never}
 */
function derived_references_self() {
	if (DEV) {
		const error = new Error(`derived_references_self\nA derived value cannot reference itself recursively\nhttps://svelte.dev/e/derived_references_self`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/derived_references_self`);
	}
}

/**
 * Keyed each block has duplicate key `%value%` at indexes %a% and %b%
 * @param {string} a
 * @param {string} b
 * @param {string | undefined | null} [value]
 * @returns {never}
 */
function each_key_duplicate(a, b, value) {
	if (DEV) {
		const error = new Error(`each_key_duplicate\n${value
			? `Keyed each block has duplicate key \`${value}\` at indexes ${a} and ${b}`
			: `Keyed each block has duplicate key at indexes ${a} and ${b}`}\nhttps://svelte.dev/e/each_key_duplicate`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/each_key_duplicate`);
	}
}

/**
 * Keyed each block has key that is not idempotent — the key for item at index %index% was `%a%` but is now `%b%`. Keys must be the same each time for a given item
 * @param {string} index
 * @param {string} a
 * @param {string} b
 * @returns {never}
 */
function each_key_volatile(index, a, b) {
	if (DEV) {
		const error = new Error(`each_key_volatile\nKeyed each block has key that is not idempotent — the key for item at index ${index} was \`${a}\` but is now \`${b}\`. Keys must be the same each time for a given item\nhttps://svelte.dev/e/each_key_volatile`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/each_key_volatile`);
	}
}

/**
 * `%rune%` cannot be used inside an effect cleanup function
 * @param {string} rune
 * @returns {never}
 */
function effect_in_teardown(rune) {
	if (DEV) {
		const error = new Error(`effect_in_teardown\n\`${rune}\` cannot be used inside an effect cleanup function\nhttps://svelte.dev/e/effect_in_teardown`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/effect_in_teardown`);
	}
}

/**
 * Effect cannot be created inside a `$derived` value that was not itself created inside an effect
 * @returns {never}
 */
function effect_in_unowned_derived() {
	if (DEV) {
		const error = new Error(`effect_in_unowned_derived\nEffect cannot be created inside a \`$derived\` value that was not itself created inside an effect\nhttps://svelte.dev/e/effect_in_unowned_derived`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/effect_in_unowned_derived`);
	}
}

/**
 * `%rune%` can only be used inside an effect (e.g. during component initialisation)
 * @param {string} rune
 * @returns {never}
 */
function effect_orphan(rune) {
	if (DEV) {
		const error = new Error(`effect_orphan\n\`${rune}\` can only be used inside an effect (e.g. during component initialisation)\nhttps://svelte.dev/e/effect_orphan`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/effect_orphan`);
	}
}

/**
 * Maximum update depth exceeded. This typically indicates that an effect reads and writes the same piece of state
 * @returns {never}
 */
function effect_update_depth_exceeded() {
	if (DEV) {
		const error = new Error(`effect_update_depth_exceeded\nMaximum update depth exceeded. This typically indicates that an effect reads and writes the same piece of state\nhttps://svelte.dev/e/effect_update_depth_exceeded`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/effect_update_depth_exceeded`);
	}
}

/**
 * Could not `{@render}` snippet due to the expression being `null` or `undefined`. Consider using optional chaining `{@render snippet?.()}`
 * @returns {never}
 */
function invalid_snippet() {
	if (DEV) {
		const error = new Error(`invalid_snippet\nCould not \`{@render}\` snippet due to the expression being \`null\` or \`undefined\`. Consider using optional chaining \`{@render snippet?.()}\`\nhttps://svelte.dev/e/invalid_snippet`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/invalid_snippet`);
	}
}

/**
 * `%name%(...)` cannot be used in runes mode
 * @param {string} name
 * @returns {never}
 */
function lifecycle_legacy_only(name) {
	if (DEV) {
		const error = new Error(`lifecycle_legacy_only\n\`${name}(...)\` cannot be used in runes mode\nhttps://svelte.dev/e/lifecycle_legacy_only`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/lifecycle_legacy_only`);
	}
}

/**
 * Cannot do `bind:%key%={undefined}` when `%key%` has a fallback value
 * @param {string} key
 * @returns {never}
 */
function props_invalid_value(key) {
	if (DEV) {
		const error = new Error(`props_invalid_value\nCannot do \`bind:${key}={undefined}\` when \`${key}\` has a fallback value\nhttps://svelte.dev/e/props_invalid_value`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/props_invalid_value`);
	}
}

/**
 * The `%rune%` rune is only available inside `.svelte` and `.svelte.js/ts` files
 * @param {string} rune
 * @returns {never}
 */
function rune_outside_svelte(rune) {
	if (DEV) {
		const error = new Error(`rune_outside_svelte\nThe \`${rune}\` rune is only available inside \`.svelte\` and \`.svelte.js/ts\` files\nhttps://svelte.dev/e/rune_outside_svelte`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/rune_outside_svelte`);
	}
}

/**
 * Property descriptors defined on `$state` objects must contain `value` and always be `enumerable`, `configurable` and `writable`.
 * @returns {never}
 */
function state_descriptors_fixed() {
	if (DEV) {
		const error = new Error(`state_descriptors_fixed\nProperty descriptors defined on \`$state\` objects must contain \`value\` and always be \`enumerable\`, \`configurable\` and \`writable\`.\nhttps://svelte.dev/e/state_descriptors_fixed`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/state_descriptors_fixed`);
	}
}

/**
 * Cannot set prototype of `$state` object
 * @returns {never}
 */
function state_prototype_fixed() {
	if (DEV) {
		const error = new Error(`state_prototype_fixed\nCannot set prototype of \`$state\` object\nhttps://svelte.dev/e/state_prototype_fixed`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/state_prototype_fixed`);
	}
}

/**
 * Updating state inside `$derived(...)`, `$inspect(...)` or a template expression is forbidden. If the value should not be reactive, declare it without `$state`
 * @returns {never}
 */
function state_unsafe_mutation() {
	if (DEV) {
		const error = new Error(`state_unsafe_mutation\nUpdating state inside \`$derived(...)\`, \`$inspect(...)\` or a template expression is forbidden. If the value should not be reactive, declare it without \`$state\`\nhttps://svelte.dev/e/state_unsafe_mutation`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/state_unsafe_mutation`);
	}
}

/**
 * A `<svelte:boundary>` `reset` function cannot be called while an error is still being handled
 * @returns {never}
 */
function svelte_boundary_reset_onerror() {
	if (DEV) {
		const error = new Error(`svelte_boundary_reset_onerror\nA \`<svelte:boundary>\` \`reset\` function cannot be called while an error is still being handled\nhttps://svelte.dev/e/svelte_boundary_reset_onerror`);

		error.name = 'Svelte error';

		throw error;
	} else {
		throw new Error(`https://svelte.dev/e/svelte_boundary_reset_onerror`);
	}
}

const EACH_ITEM_REACTIVE = 1;
const EACH_INDEX_REACTIVE = 1 << 1;
/** See EachBlock interface metadata.is_controlled for an explanation what this is */
const EACH_IS_CONTROLLED = 1 << 2;
const EACH_IS_ANIMATED = 1 << 3;
const EACH_ITEM_IMMUTABLE = 1 << 4;

const PROPS_IS_IMMUTABLE = 1;
const PROPS_IS_RUNES = 1 << 1;
const PROPS_IS_UPDATED = 1 << 2;
const PROPS_IS_BINDABLE = 1 << 3;
const PROPS_IS_LAZY_INITIAL = 1 << 4;

const TRANSITION_IN = 1;
const TRANSITION_OUT = 1 << 1;
const TRANSITION_GLOBAL = 1 << 2;

const TEMPLATE_FRAGMENT = 1;
const TEMPLATE_USE_IMPORT_NODE = 1 << 1;

const UNINITIALIZED = Symbol();

// Dev-time component properties
const FILENAME = Symbol('filename');

const NAMESPACE_HTML = 'http://www.w3.org/1999/xhtml';
const NAMESPACE_SVG = 'http://www.w3.org/2000/svg';
const NAMESPACE_MATHML = 'http://www.w3.org/1998/Math/MathML';

/* This file is generated by scripts/process-messages/index.js. Do not edit! */


var bold = 'font-weight: bold';
var normal = 'font-weight: normal';

/**
 * Detected reactivity loss when reading `%name%`. This happens when state is read in an async function after an earlier `await`
 * @param {string} name
 */
function await_reactivity_loss(name) {
	if (DEV) {
		console.warn(`%c[svelte] await_reactivity_loss\n%cDetected reactivity loss when reading \`${name}\`. This happens when state is read in an async function after an earlier \`await\`\nhttps://svelte.dev/e/await_reactivity_loss`, bold, normal);
	} else {
		console.warn(`https://svelte.dev/e/await_reactivity_loss`);
	}
}

/**
 * An async derived, `%name%` (%location%) was not read immediately after it resolved. This often indicates an unnecessary waterfall, which can slow down your app
 * @param {string} name
 * @param {string} location
 */
function await_waterfall(name, location) {
	if (DEV) {
		console.warn(`%c[svelte] await_waterfall\n%cAn async derived, \`${name}\` (${location}) was not read immediately after it resolved. This often indicates an unnecessary waterfall, which can slow down your app\nhttps://svelte.dev/e/await_waterfall`, bold, normal);
	} else {
		console.warn(`https://svelte.dev/e/await_waterfall`);
	}
}

/**
 * %handler% should be a function. Did you mean to %suggestion%?
 * @param {string} handler
 * @param {string} suggestion
 */
function event_handler_invalid(handler, suggestion) {
	if (DEV) {
		console.warn(`%c[svelte] event_handler_invalid\n%c${handler} should be a function. Did you mean to ${suggestion}?\nhttps://svelte.dev/e/event_handler_invalid`, bold, normal);
	} else {
		console.warn(`https://svelte.dev/e/event_handler_invalid`);
	}
}

/**
 * %parent% passed property `%prop%` to %child% with `bind:`, but its parent component %owner% did not declare `%prop%` as a binding. Consider creating a binding between %owner% and %parent% (e.g. `bind:%prop%={...}` instead of `%prop%={...}`)
 * @param {string} parent
 * @param {string} prop
 * @param {string} child
 * @param {string} owner
 */
function ownership_invalid_binding(parent, prop, child, owner) {
	if (DEV) {
		console.warn(`%c[svelte] ownership_invalid_binding\n%c${parent} passed property \`${prop}\` to ${child} with \`bind:\`, but its parent component ${owner} did not declare \`${prop}\` as a binding. Consider creating a binding between ${owner} and ${parent} (e.g. \`bind:${prop}={...}\` instead of \`${prop}={...}\`)\nhttps://svelte.dev/e/ownership_invalid_binding`, bold, normal);
	} else {
		console.warn(`https://svelte.dev/e/ownership_invalid_binding`);
	}
}

/**
 * Mutating unbound props (`%name%`, at %location%) is strongly discouraged. Consider using `bind:%prop%={...}` in %parent% (or using a callback) instead
 * @param {string} name
 * @param {string} location
 * @param {string} prop
 * @param {string} parent
 */
function ownership_invalid_mutation(name, location, prop, parent) {
	if (DEV) {
		console.warn(`%c[svelte] ownership_invalid_mutation\n%cMutating unbound props (\`${name}\`, at ${location}) is strongly discouraged. Consider using \`bind:${prop}={...}\` in ${parent} (or using a callback) instead\nhttps://svelte.dev/e/ownership_invalid_mutation`, bold, normal);
	} else {
		console.warn(`https://svelte.dev/e/ownership_invalid_mutation`);
	}
}

/**
 * The `value` property of a `<select multiple>` element should be an array, but it received a non-array value. The selection will be kept as is.
 */
function select_multiple_invalid_value() {
	if (DEV) {
		console.warn(`%c[svelte] select_multiple_invalid_value\n%cThe \`value\` property of a \`<select multiple>\` element should be an array, but it received a non-array value. The selection will be kept as is.\nhttps://svelte.dev/e/select_multiple_invalid_value`, bold, normal);
	} else {
		console.warn(`https://svelte.dev/e/select_multiple_invalid_value`);
	}
}

/**
 * Reactive `$state(...)` proxies and the values they proxy have different identities. Because of this, comparisons with `%operator%` will produce unexpected results
 * @param {string} operator
 */
function state_proxy_equality_mismatch(operator) {
	if (DEV) {
		console.warn(`%c[svelte] state_proxy_equality_mismatch\n%cReactive \`$state(...)\` proxies and the values they proxy have different identities. Because of this, comparisons with \`${operator}\` will produce unexpected results\nhttps://svelte.dev/e/state_proxy_equality_mismatch`, bold, normal);
	} else {
		console.warn(`https://svelte.dev/e/state_proxy_equality_mismatch`);
	}
}

/**
 * A `<svelte:boundary>` `reset` function only resets the boundary the first time it is called
 */
function svelte_boundary_reset_noop() {
	if (DEV) {
		console.warn(`%c[svelte] svelte_boundary_reset_noop\n%cA \`<svelte:boundary>\` \`reset\` function only resets the boundary the first time it is called\nhttps://svelte.dev/e/svelte_boundary_reset_noop`, bold, normal);
	} else {
		console.warn(`https://svelte.dev/e/svelte_boundary_reset_noop`);
	}
}

/**
 * The `slide` transition does not work correctly for elements with `display: %value%`
 * @param {string} value
 */
function transition_slide_display(value) {
	if (DEV) {
		console.warn(`%c[svelte] transition_slide_display\n%cThe \`slide\` transition does not work correctly for elements with \`display: ${value}\`\nhttps://svelte.dev/e/transition_slide_display`, bold, normal);
	} else {
		console.warn(`https://svelte.dev/e/transition_slide_display`);
	}
}

/** @import { TemplateNode } from '#client' */


/**
 * Use this variable to guard everything related to hydration code so it can be treeshaken out
 * if the user doesn't use the `hydrate` method and these code paths are therefore not needed.
 */
let hydrating = false;

/** @param {TemplateNode} node */
function reset(node) {
	return;
}

function next(count = 1) {
}

/** @import { Equals } from '#client' */

/** @type {Equals} */
function equals$1(value) {
	return value === this.v;
}

/**
 * @param {unknown} a
 * @param {unknown} b
 * @returns {boolean}
 */
function safe_not_equal(a, b) {
	return a != a
		? b == b
		: a !== b || (a !== null && typeof a === 'object') || typeof a === 'function';
}

/** @type {Equals} */
function safe_equals(value) {
	return !safe_not_equal(value, this.v);
}

/** True if experimental.async=true */
let async_mode_flag = false;
/** True if we're not certain that we only have Svelte 5 code in the compilation */
let legacy_mode_flag = false;
/** True if $inspect.trace is used */
let tracing_mode_flag = false;

function enable_legacy_mode_flag() {
	legacy_mode_flag = true;
}

/** @import { Derived, Reaction, Value } from '#client' */

/**
 * @param {Value} source
 * @param {string} label
 */
function tag(source, label) {
	source.label = label;
	tag_proxy(source.v, label);

	return source;
}

/**
 * @param {unknown} value
 * @param {string} label
 */
function tag_proxy(value, label) {
	// @ts-expect-error
	value?.[PROXY_PATH_SYMBOL]?.(label);
	return value;
}

/**
 * @param {string} label
 * @returns {Error & { stack: string } | null}
 */
function get_error(label) {
	const error = new Error();
	const stack = get_stack();

	if (stack.length === 0) {
		return null;
	}

	stack.unshift('\n');

	define_property(error, 'stack', {
		value: stack.join('\n')
	});

	define_property(error, 'name', {
		value: label
	});

	return /** @type {Error & { stack: string }} */ (error);
}

/**
 * @returns {string[]}
 */
function get_stack() {
	// @ts-ignore - doesn't exist everywhere
	const limit = Error.stackTraceLimit;
	// @ts-ignore - doesn't exist everywhere
	Error.stackTraceLimit = Infinity;
	const stack = new Error().stack;
	// @ts-ignore - doesn't exist everywhere
	Error.stackTraceLimit = limit;

	if (!stack) return [];

	const lines = stack.split('\n');
	const new_lines = [];

	for (let i = 0; i < lines.length; i++) {
		const line = lines[i];
		const posixified = line.replaceAll('\\', '/');

		if (line.trim() === 'Error') {
			continue;
		}

		if (line.includes('validate_each_keys')) {
			return [];
		}

		if (posixified.includes('svelte/src/internal') || posixified.includes('node_modules/.vite')) {
			continue;
		}

		new_lines.push(line);
	}

	return new_lines;
}

/**
 * @param {boolean} condition
 * @param {string} message
 */
function invariant(condition, message) {
	if (!DEV) {
		throw new Error('invariant(...) was not guarded by if (DEV)');
	}

	if (!condition) invariant_violation(message);
}

/** @import { ComponentContext, DevStackEntry, Effect } from '#client' */

/** @type {ComponentContext | null} */
let component_context = null;

/** @param {ComponentContext | null} context */
function set_component_context(context) {
	component_context = context;
}

/** @type {DevStackEntry | null} */
let dev_stack = null;

/** @param {DevStackEntry | null} stack */
function set_dev_stack(stack) {
	dev_stack = stack;
}

/**
 * Execute a callback with a new dev stack entry
 * @param {() => any} callback - Function to execute
 * @param {DevStackEntry['type']} type - Type of block/component
 * @param {any} component - Component function
 * @param {number} line - Line number
 * @param {number} column - Column number
 * @param {Record<string, any>} [additional] - Any additional properties to add to the dev stack entry
 * @returns {any}
 */
function add_svelte_meta(callback, type, component, line, column, additional) {
	const parent = dev_stack;

	dev_stack = {
		type,
		file: component[FILENAME],
		line,
		column,
		parent,
		...additional
	};

	try {
		return callback();
	} finally {
		dev_stack = parent;
	}
}

/**
 * The current component function. Different from current component context:
 * ```html
 * <!-- App.svelte -->
 * <Foo>
 *   <Bar /> <!-- context == Foo.svelte, function == App.svelte -->
 * </Foo>
 * ```
 * @type {ComponentContext['function']}
 */
let dev_current_component_function = null;

/** @param {ComponentContext['function']} fn */
function set_dev_current_component_function(fn) {
	dev_current_component_function = fn;
}

/**
 * Retrieves the context that belongs to the closest parent component with the specified `key`.
 * Must be called during component initialisation.
 *
 * [`createContext`](https://svelte.dev/docs/svelte/svelte#createContext) is a type-safe alternative.
 *
 * @template T
 * @param {any} key
 * @returns {T}
 */
function getContext(key) {
	const context_map = get_or_init_context_map('getContext');
	const result = /** @type {T} */ (context_map.get(key));
	return result;
}

/**
 * Associates an arbitrary `context` object with the current component and the specified `key`
 * and returns that object. The context is then available to children of the component
 * (including slotted content) with `getContext`.
 *
 * Like lifecycle functions, this must be called during component initialisation.
 *
 * [`createContext`](https://svelte.dev/docs/svelte/svelte#createContext) is a type-safe alternative.
 *
 * @template T
 * @param {any} key
 * @param {T} context
 * @returns {T}
 */
function setContext(key, context) {
	const context_map = get_or_init_context_map('setContext');

	context_map.set(key, context);
	return context;
}

/**
 * Checks whether a given `key` has been set in the context of a parent component.
 * Must be called during component initialisation.
 *
 * @param {any} key
 * @returns {boolean}
 */
function hasContext(key) {
	const context_map = get_or_init_context_map('hasContext');
	return context_map.has(key);
}

/**
 * @param {Record<string, unknown>} props
 * @param {any} runes
 * @param {Function} [fn]
 * @returns {void}
 */
function push$1(props, runes = false, fn) {
	component_context = {
		p: component_context,
		i: false,
		c: null,
		e: null,
		s: props,
		x: null,
		r: /** @type {Effect} */ (active_effect),
		l: legacy_mode_flag && !runes ? { s: null, u: null, $: [] } : null
	};

	if (DEV) {
		// component function
		component_context.function = fn;
		dev_current_component_function = fn;
	}
}

/**
 * @template {Record<string, any>} T
 * @param {T} [component]
 * @returns {T}
 */
function pop(component) {
	var context = /** @type {ComponentContext} */ (component_context);
	var effects = context.e;

	if (effects !== null) {
		context.e = null;

		for (var fn of effects) {
			create_user_effect(fn);
		}
	}

	if (component !== undefined) {
		context.x = component;
	}

	context.i = true;

	component_context = context.p;

	if (DEV) {
		dev_current_component_function = component_context?.function ?? null;
	}

	return component ?? /** @type {T} */ ({});
}

/** @returns {boolean} */
function is_runes() {
	return !legacy_mode_flag || (component_context !== null && component_context.l === null);
}

/**
 * @param {string} name
 * @returns {Map<unknown, unknown>}
 */
function get_or_init_context_map(name) {
	if (component_context === null) {
		lifecycle_outside_component(name);
	}

	return (component_context.c ??= new Map(get_parent_context(component_context) || undefined));
}

/**
 * @param {ComponentContext} component_context
 * @returns {Map<unknown, unknown> | null}
 */
function get_parent_context(component_context) {
	let parent = component_context.p;
	while (parent !== null) {
		const context_map = parent.c;
		if (context_map !== null) {
			return context_map;
		}
		parent = parent.p;
	}
	return null;
}

/** @type {Array<() => void>} */
let micro_tasks = [];

function run_micro_tasks() {
	var tasks = micro_tasks;
	micro_tasks = [];
	run_all(tasks);
}

/**
 * @param {() => void} fn
 */
function queue_micro_task(fn) {
	if (micro_tasks.length === 0 && !is_flushing_sync) {
		var tasks = micro_tasks;
		queueMicrotask(() => {
			// If this is false, a flushSync happened in the meantime. Do _not_ run new scheduled microtasks in that case
			// as the ordering of microtasks would be broken at that point - consider this case:
			// - queue_micro_task schedules microtask A to flush task X
			// - synchronously after, flushSync runs, processing task X
			// - synchronously after, some other microtask B is scheduled, but not through queue_micro_task but for example a Promise.resolve() in user code
			// - synchronously after, queue_micro_task schedules microtask C to flush task Y
			// - one tick later, microtask A now resolves, flushing task Y before microtask B, which is incorrect
			// This if check prevents that race condition (that realistically will only happen in tests)
			if (tasks === micro_tasks) run_micro_tasks();
		});
	}

	micro_tasks.push(fn);
}

/**
 * Synchronously run any queued tasks.
 */
function flush_tasks() {
	while (micro_tasks.length > 0) {
		run_micro_tasks();
	}
}

/** @import { Derived, Effect } from '#client' */
/** @import { Boundary } from './dom/blocks/boundary.js' */

const adjustments = new WeakMap();

/**
 * @param {unknown} error
 */
function handle_error(error) {
	var effect = active_effect;

	// for unowned deriveds, don't throw until we read the value
	if (effect === null) {
		/** @type {Derived} */ (active_reaction).f |= ERROR_VALUE;
		return error;
	}

	if (DEV && error instanceof Error && !adjustments.has(error)) {
		adjustments.set(error, get_adjustments(error, effect));
	}

	// if the error occurred while creating this subtree, we let it
	// bubble up until it hits a boundary that can handle it, unless
	// it's an $effect in which case it doesn't run immediately
	if ((effect.f & REACTION_RAN) === 0 && (effect.f & EFFECT) === 0) {
		if (DEV && !effect.parent && error instanceof Error) {
			apply_adjustments(error);
		}

		throw error;
	}

	// otherwise we bubble up the effect tree ourselves
	invoke_error_boundary(error, effect);
}

/**
 * @param {unknown} error
 * @param {Effect | null} effect
 */
function invoke_error_boundary(error, effect) {
	while (effect !== null) {
		if ((effect.f & BOUNDARY_EFFECT) !== 0) {
			if ((effect.f & REACTION_RAN) === 0) {
				// we are still creating the boundary effect
				throw error;
			}

			try {
				/** @type {Boundary} */ (effect.b).error(error);
				return;
			} catch (e) {
				error = e;
			}
		}

		effect = effect.parent;
	}

	if (DEV && error instanceof Error) {
		apply_adjustments(error);
	}

	throw error;
}

/**
 * Add useful information to the error message/stack in development
 * @param {Error} error
 * @param {Effect} effect
 */
function get_adjustments(error, effect) {
	const message_descriptor = get_descriptor(error, 'message');

	// if the message was already changed and it's not configurable we can't change it
	// or it will throw a different error swallowing the original error
	if (message_descriptor && !message_descriptor.configurable) return;

	var indent = is_firefox ? '  ' : '\t';
	var component_stack = `\n${indent}in ${effect.fn?.name || '<unknown>'}`;
	var context = effect.ctx;

	while (context !== null) {
		component_stack += `\n${indent}in ${context.function?.[FILENAME].split('/').pop()}`;
		context = context.p;
	}

	return {
		message: error.message + `\n${component_stack}\n`,
		stack: error.stack
			?.split('\n')
			.filter((line) => !line.includes('svelte/src/internal'))
			.join('\n')
	};
}

/**
 * @param {Error} error
 */
function apply_adjustments(error) {
	const adjusted = adjustments.get(error);

	if (adjusted) {
		define_property(error, 'message', {
			value: adjusted.message
		});

		define_property(error, 'stack', {
			value: adjusted.stack
		});
	}
}

/** @import { Derived, Signal } from '#client' */

const STATUS_MASK = ~(DIRTY | MAYBE_DIRTY | CLEAN);

/**
 * @param {Signal} signal
 * @param {number} status
 */
function set_signal_status(signal, status) {
	signal.f = (signal.f & STATUS_MASK) | status;
}

/**
 * Set a derived's status to CLEAN or MAYBE_DIRTY based on its connection state.
 * @param {Derived} derived
 */
function update_derived_status(derived) {
	// Only mark as MAYBE_DIRTY if disconnected and has dependencies.
	if ((derived.f & CONNECTED) !== 0 || derived.deps === null) {
		set_signal_status(derived, CLEAN);
	} else {
		set_signal_status(derived, MAYBE_DIRTY);
	}
}

/** @import { Derived, Effect, Value } from '#client' */

/**
 * @param {Value[] | null} deps
 */
function clear_marked(deps) {
	if (deps === null) return;

	for (const dep of deps) {
		if ((dep.f & DERIVED) === 0 || (dep.f & WAS_MARKED) === 0) {
			continue;
		}

		dep.f ^= WAS_MARKED;

		clear_marked(/** @type {Derived} */ (dep).deps);
	}
}

/**
 * @param {Effect} effect
 * @param {Set<Effect>} dirty_effects
 * @param {Set<Effect>} maybe_dirty_effects
 */
function defer_effect(effect, dirty_effects, maybe_dirty_effects) {
	if ((effect.f & DIRTY) !== 0) {
		dirty_effects.add(effect);
	} else if ((effect.f & MAYBE_DIRTY) !== 0) {
		maybe_dirty_effects.add(effect);
	}

	// Since we're not executing these effects now, we need to clear any WAS_MARKED flags
	// so that other batches can correctly reach these effects during their own traversal
	clear_marked(effect.deps);

	// mark as clean so they get scheduled if they depend on pending async state
	set_signal_status(effect, CLEAN);
}

/** @import { Readable } from './public' */

/**
 * @template T
 * @param {Readable<T> | null | undefined} store
 * @param {(value: T) => void} run
 * @param {(value: T) => void} [invalidate]
 * @returns {() => void}
 */
function subscribe_to_store(store, run, invalidate) {
	if (store == null) {
		// @ts-expect-error
		run(undefined);

		// @ts-expect-error
		if (invalidate) invalidate(undefined);

		return noop;
	}

	// Svelte store takes a private second argument
	// StartStopNotifier could mutate state, and we want to silence the corresponding validation error
	const unsub = untrack(() =>
		store.subscribe(
			run,
			// @ts-expect-error
			invalidate
		)
	);

	// Also support RxJS
	// @ts-expect-error TODO fix this in the types?
	return unsub.unsubscribe ? () => unsub.unsubscribe() : unsub;
}

/** @import { Readable, StartStopNotifier, Subscriber, Unsubscriber, Updater, Writable } from '../public.js' */
/** @import { Stores, StoresValues, SubscribeInvalidateTuple } from '../private.js' */

/**
 * @type {Array<SubscribeInvalidateTuple<any> | any>}
 */
const subscriber_queue = [];

/**
 * Creates a `Readable` store that allows reading by subscription.
 *
 * @template T
 * @param {T} [value] initial value
 * @param {StartStopNotifier<T>} [start]
 * @returns {Readable<T>}
 */
function readable(value, start) {
	return {
		subscribe: writable(value, start).subscribe
	};
}

/**
 * Create a `Writable` store that allows both updating and reading by subscription.
 *
 * @template T
 * @param {T} [value] initial value
 * @param {StartStopNotifier<T>} [start]
 * @returns {Writable<T>}
 */
function writable(value, start = noop) {
	/** @type {Unsubscriber | null} */
	let stop = null;

	/** @type {Set<SubscribeInvalidateTuple<T>>} */
	const subscribers = new Set();

	/**
	 * @param {T} new_value
	 * @returns {void}
	 */
	function set(new_value) {
		if (safe_not_equal(value, new_value)) {
			value = new_value;
			if (stop) {
				// store is ready
				const run_queue = !subscriber_queue.length;
				for (const subscriber of subscribers) {
					subscriber[1]();
					subscriber_queue.push(subscriber, value);
				}
				if (run_queue) {
					for (let i = 0; i < subscriber_queue.length; i += 2) {
						subscriber_queue[i][0](subscriber_queue[i + 1]);
					}
					subscriber_queue.length = 0;
				}
			}
		}
	}

	/**
	 * @param {Updater<T>} fn
	 * @returns {void}
	 */
	function update(fn) {
		set(fn(/** @type {T} */ (value)));
	}

	/**
	 * @param {Subscriber<T>} run
	 * @param {() => void} [invalidate]
	 * @returns {Unsubscriber}
	 */
	function subscribe(run, invalidate = noop) {
		/** @type {SubscribeInvalidateTuple<T>} */
		const subscriber = [run, invalidate];
		subscribers.add(subscriber);
		if (subscribers.size === 1) {
			stop = start(set, update) || noop;
		}
		run(/** @type {T} */ (value));
		return () => {
			subscribers.delete(subscriber);
			if (subscribers.size === 0 && stop) {
				stop();
				stop = null;
			}
		};
	}
	return { set, update, subscribe };
}

/**
 * Derived value store by synchronizing one or more readable stores and
 * applying an aggregation function over its input values.
 *
 * @template {Stores} S
 * @template T
 * @overload
 * @param {S} stores
 * @param {(values: StoresValues<S>, set: (value: T) => void, update: (fn: Updater<T>) => void) => Unsubscriber | void} fn
 * @param {T} [initial_value]
 * @returns {Readable<T>}
 */
/**
 * Derived value store by synchronizing one or more readable stores and
 * applying an aggregation function over its input values.
 *
 * @template {Stores} S
 * @template T
 * @overload
 * @param {S} stores
 * @param {(values: StoresValues<S>) => T} fn
 * @param {T} [initial_value]
 * @returns {Readable<T>}
 */
/**
 * @template {Stores} S
 * @template T
 * @param {S} stores
 * @param {Function} fn
 * @param {T} [initial_value]
 * @returns {Readable<T>}
 */
function derived$1(stores, fn, initial_value) {
	const single = !Array.isArray(stores);
	/** @type {Array<Readable<any>>} */
	const stores_array = single ? [stores] : stores;
	if (!stores_array.every(Boolean)) {
		throw new Error('derived() expects stores as input, got a falsy value');
	}
	const auto = fn.length < 2;
	return readable(initial_value, (set, update) => {
		let started = false;
		/** @type {T[]} */
		const values = [];
		let pending = 0;
		let cleanup = noop;
		const sync = () => {
			if (pending) {
				return;
			}
			cleanup();
			const result = fn(single ? values[0] : values, set, update);
			if (auto) {
				set(result);
			} else {
				cleanup = typeof result === 'function' ? result : noop;
			}
		};
		const unsubscribers = stores_array.map((store, i) =>
			subscribe_to_store(
				store,
				(value) => {
					values[i] = value;
					pending &= ~(1 << i);
					if (started) {
						sync();
					}
				},
				() => {
					pending |= 1 << i;
				}
			)
		);
		started = true;
		sync();
		return function stop() {
			run_all(unsubscribers);
			cleanup();
			// We need to set this to false because callbacks can still happen despite having unsubscribed:
			// Callbacks might already be placed in the queue which doesn't know it should no longer
			// invoke this derived store.
			started = false;
		};
	});
}

/**
 * Get the current value from a store by subscribing and immediately unsubscribing.
 *
 * @template T
 * @param {Readable<T>} store
 * @returns {T}
 */
function get$1(store) {
	let value;
	subscribe_to_store(store, (_) => (value = _))();
	// @ts-expect-error
	return value;
}

/** @import { StoreReferencesContainer } from '#client' */
/** @import { Store } from '#shared' */

/**
 * We set this to `true` when updating a store so that we correctly
 * schedule effects if the update takes place inside a `$:` effect
 */
let legacy_is_updating_store = false;

/**
 * Whether or not the prop currently being read is a store binding, as in
 * `<Child bind:x={$y} />`. If it is, we treat the prop as mutable even in
 * runes mode, and skip `binding_property_non_reactive` validation
 */
let is_store_binding = false;

let IS_UNMOUNTED = Symbol();

/**
 * Gets the current value of a store. If the store isn't subscribed to yet, it will create a proxy
 * signal that will be updated when the store is. The store references container is needed to
 * track reassignments to stores and to track the correct component context.
 * @template V
 * @param {Store<V> | null | undefined} store
 * @param {string} store_name
 * @param {StoreReferencesContainer} stores
 * @returns {V}
 */
function store_get(store, store_name, stores) {
	const entry = (stores[store_name] ??= {
		store: null,
		source: mutable_source(undefined),
		unsubscribe: noop
	});

	if (DEV) {
		entry.source.label = store_name;
	}

	// if the component that setup this is already unmounted we don't want to register a subscription
	if (entry.store !== store && !(IS_UNMOUNTED in stores)) {
		entry.unsubscribe();
		entry.store = store ?? null;

		if (store == null) {
			entry.source.v = undefined; // see synchronous callback comment below
			entry.unsubscribe = noop;
		} else {
			var is_synchronous_callback = true;

			entry.unsubscribe = subscribe_to_store(store, (v) => {
				if (is_synchronous_callback) {
					// If the first updates to the store value (possibly multiple of them) are synchronously
					// inside a derived, we will hit the `state_unsafe_mutation` error if we `set` the value
					entry.source.v = v;
				} else {
					set(entry.source, v);
				}
			});

			is_synchronous_callback = false;
		}
	}

	// if the component that setup this stores is already unmounted the source will be out of sync
	// so we just use the `get` for the stores, less performant but it avoids to create a memory leak
	// and it will keep the value consistent
	if (store && IS_UNMOUNTED in stores) {
		return get$1(store);
	}

	return get(entry.source);
}

/**
 * Unsubscribe from a store if it's not the same as the one in the store references container.
 * We need this in addition to `store_get` because someone could unsubscribe from a store but
 * then never subscribe to the new one (if any), causing the subscription to stay open wrongfully.
 * @param {Store<any> | null | undefined} store
 * @param {string} store_name
 * @param {StoreReferencesContainer} stores
 */
function store_unsub(store, store_name, stores) {
	/** @type {StoreReferencesContainer[''] | undefined} */
	let entry = stores[store_name];

	if (entry && entry.store !== store) {
		// Don't reset store yet, so that store_get above can resubscribe to new store if necessary
		entry.unsubscribe();
		entry.unsubscribe = noop;
	}

	return store;
}

/**
 * Sets the new value of a store and returns that value.
 * @template V
 * @param {Store<V>} store
 * @param {V} value
 * @returns {V}
 */
function store_set(store, value) {
	update_with_flag(store, value);
	return value;
}

/**
 * Unsubscribes from all auto-subscribed stores on destroy
 * @returns {[StoreReferencesContainer, ()=>void]}
 */
function setup_stores() {
	/** @type {StoreReferencesContainer} */
	const stores = {};

	function cleanup() {
		teardown(() => {
			for (var store_name in stores) {
				const ref = stores[store_name];
				ref.unsubscribe();
			}
			define_property(stores, IS_UNMOUNTED, {
				enumerable: false,
				value: true
			});
		});
	}

	return [stores, cleanup];
}

/**
 * @param {Store<V>} store
 * @param {V} value
 * @template V
 */
function update_with_flag(store, value) {
	legacy_is_updating_store = true;

	try {
		store.set(value);
	} finally {
		legacy_is_updating_store = false;
	}
}

/**
 * Updates a store with a new value.
 * @param {Store<V>} store  the store to update
 * @param {any} expression  the expression that mutates the store
 * @param {V} new_value  the new store value
 * @template V
 */
function store_mutate(store, expression, new_value) {
	update_with_flag(store, new_value);
	return expression;
}

/**
 * Returns a tuple that indicates whether `fn()` reads a prop that is a store binding.
 * Used to prevent `binding_property_non_reactive` validation false positives and
 * ensure that these props are treated as mutable even in runes mode
 * @template T
 * @param {() => T} fn
 * @returns {[T, boolean]}
 */
function capture_store_binding(fn) {
	var previous_is_store_binding = is_store_binding;

	try {
		is_store_binding = false;
		return [fn(), is_store_binding];
	} finally {
		is_store_binding = previous_is_store_binding;
	}
}

/** @import { Fork } from 'svelte' */
/** @import { Derived, Effect, Reaction, Source, Value } from '#client' */

/** @type {Set<Batch>} */
const batches = new Set();

/** @type {Batch | null} */
let current_batch = null;

/**
 * When time travelling (i.e. working in one batch, while other batches
 * still have ongoing work), we ignore the real values of affected
 * signals in favour of their values within the batch
 * @type {Map<Value, any> | null}
 */
let batch_values = null;

/** @type {Effect | null} */
let last_scheduled_effect = null;

let is_flushing_sync = false;
let is_processing = false;

/**
 * During traversal, this is an array. Newly created effects are (if not immediately
 * executed) pushed to this array, rather than going through the scheduling
 * rigamarole that would cause another turn of the flush loop.
 * @type {Effect[] | null}
 */
let collected_effects = null;

/**
 * An array of effects that are marked during traversal as a result of a `set`
 * (not `internal_set`) call. These will be added to the next batch and
 * trigger another `batch.process()`
 * @type {Effect[] | null}
 * @deprecated when we get rid of legacy mode and stores, we can get rid of this
 */
let legacy_updates = null;

var flush_count = 0;
var source_stacks = DEV ? new Set() : null;

let uid = 1;

class Batch {
	id = uid++;

	/**
	 * The current values of any signals that are updated in this batch.
	 * Tuple format: [value, is_derived] (note: is_derived is false for deriveds, too, if they were overridden via assignment)
	 * They keys of this map are identical to `this.#previous`
	 * @type {Map<Value, [any, boolean]>}
	 */
	current = new Map();

	/**
	 * The values of any signals (sources and deriveds) that are updated in this batch _before_ those updates took place.
	 * They keys of this map are identical to `this.#current`
	 * @type {Map<Value, any>}
	 */
	previous = new Map();

	/**
	 * When the batch is committed (and the DOM is updated), we need to remove old branches
	 * and append new ones by calling the functions added inside (if/each/key/etc) blocks
	 * @type {Set<(batch: Batch) => void>}
	 */
	#commit_callbacks = new Set();

	/**
	 * If a fork is discarded, we need to destroy any effects that are no longer needed
	 * @type {Set<(batch: Batch) => void>}
	 */
	#discard_callbacks = new Set();

	/**
	 * Async effects that are currently in flight
	 * @type {Map<Effect, number>}
	 */
	#pending = new Map();

	/**
	 * Async effects that are currently in flight, _not_ inside a pending boundary
	 * @type {Map<Effect, number>}
	 */
	#blocking_pending = new Map();

	/**
	 * A deferred that resolves when the batch is committed, used with `settled()`
	 * TODO replace with Promise.withResolvers once supported widely enough
	 * @type {{ promise: Promise<void>, resolve: (value?: any) => void, reject: (reason: unknown) => void } | null}
	 */
	#deferred = null;

	/**
	 * The root effects that need to be flushed
	 * @type {Effect[]}
	 */
	#roots = [];

	/**
	 * Effects created while this batch was active.
	 * @type {Effect[]}
	 */
	#new_effects = [];

	/**
	 * Deferred effects (which run after async work has completed) that are DIRTY
	 * @type {Set<Effect>}
	 */
	#dirty_effects = new Set();

	/**
	 * Deferred effects that are MAYBE_DIRTY
	 * @type {Set<Effect>}
	 */
	#maybe_dirty_effects = new Set();

	/**
	 * A map of branches that still exist, but will be destroyed when this batch
	 * is committed — we skip over these during `process`.
	 * The value contains child effects that were dirty/maybe_dirty before being reset,
	 * so they can be rescheduled if the branch survives.
	 * @type {Map<Effect, { d: Effect[], m: Effect[] }>}
	 */
	#skipped_branches = new Map();

	/**
	 * Inverse of #skipped_branches which we need to tell prior batches to unskip them when committing
	 * @type {Set<Effect>}
	 */
	#unskipped_branches = new Set();

	is_fork = false;

	#decrement_queued = false;

	/** @type {Set<Batch>} */
	#blockers = new Set();

	#is_deferred() {
		return this.is_fork || this.#blocking_pending.size > 0;
	}

	#is_blocked() {
		for (const batch of this.#blockers) {
			for (const effect of batch.#blocking_pending.keys()) {
				var skipped = false;
				var e = effect;

				while (e.parent !== null) {
					if (this.#skipped_branches.has(e)) {
						skipped = true;
						break;
					}

					e = e.parent;
				}

				if (!skipped) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Add an effect to the #skipped_branches map and reset its children
	 * @param {Effect} effect
	 */
	skip_effect(effect) {
		if (!this.#skipped_branches.has(effect)) {
			this.#skipped_branches.set(effect, { d: [], m: [] });
		}
		this.#unskipped_branches.delete(effect);
	}

	/**
	 * Remove an effect from the #skipped_branches map and reschedule
	 * any tracked dirty/maybe_dirty child effects
	 * @param {Effect} effect
	 * @param {(e: Effect) => void} callback
	 */
	unskip_effect(effect, callback = (e) => this.schedule(e)) {
		var tracked = this.#skipped_branches.get(effect);
		if (tracked) {
			this.#skipped_branches.delete(effect);

			for (var e of tracked.d) {
				set_signal_status(e, DIRTY);
				callback(e);
			}

			for (e of tracked.m) {
				set_signal_status(e, MAYBE_DIRTY);
				callback(e);
			}
		}
		this.#unskipped_branches.add(effect);
	}

	#process() {
		if (flush_count++ > 1000) {
			batches.delete(this);
			infinite_loop_guard();
		}

		// we only reschedule previously-deferred effects if we expect
		// to be able to run them after processing the batch
		if (!this.#is_deferred()) {
			for (const e of this.#dirty_effects) {
				this.#maybe_dirty_effects.delete(e);
				set_signal_status(e, DIRTY);
				this.schedule(e);
			}

			for (const e of this.#maybe_dirty_effects) {
				set_signal_status(e, MAYBE_DIRTY);
				this.schedule(e);
			}
		}

		const roots = this.#roots;
		this.#roots = [];

		this.apply();

		/** @type {Effect[]} */
		var effects = (collected_effects = []);

		/** @type {Effect[]} */
		var render_effects = [];

		/**
		 * @type {Effect[]}
		 * @deprecated when we get rid of legacy mode and stores, we can get rid of this
		 */
		var updates = (legacy_updates = []);

		for (const root of roots) {
			try {
				this.#traverse(root, effects, render_effects);
			} catch (e) {
				reset_all(root);
				throw e;
			}
		}

		// any writes should take effect in a subsequent batch
		current_batch = null;

		if (updates.length > 0) {
			var batch = Batch.ensure();
			for (const e of updates) {
				batch.schedule(e);
			}
		}

		collected_effects = null;
		legacy_updates = null;

		if (this.#is_deferred() || this.#is_blocked()) {
			this.#defer_effects(render_effects);
			this.#defer_effects(effects);

			for (const [e, t] of this.#skipped_branches) {
				reset_branch(e, t);
			}
		} else {
			if (this.#pending.size === 0) {
				batches.delete(this);
			}

			// clear effects. Those that are still needed will be rescheduled through unskipping the skipped branches.
			this.#dirty_effects.clear();
			this.#maybe_dirty_effects.clear();

			// append/remove branches
			for (const fn of this.#commit_callbacks) fn(this);
			this.#commit_callbacks.clear();
			flush_queued_effects(render_effects);
			flush_queued_effects(effects);

			this.#deferred?.resolve();
		}

		var next_batch = /** @type {Batch | null} */ (/** @type {unknown} */ (current_batch));

		// Edge case: During traversal new branches might create effects that run immediately and set state,
		// causing an effect and therefore a root to be scheduled again. We need to traverse the current batch
		// once more in that case - most of the time this will just clean up dirty branches.
		if (this.#roots.length > 0) {
			const batch = (next_batch ??= this);
			batch.#roots.push(...this.#roots.filter((r) => !batch.#roots.includes(r)));
		}

		if (next_batch !== null) {
			batches.add(next_batch);

			if (DEV) {
				for (const source of this.current.keys()) {
					/** @type {Set<Source>} */ (source_stacks).add(source);
				}
			}

			next_batch.#process();
		}
	}

	/**
	 * Traverse the effect tree, executing effects or stashing
	 * them for later execution as appropriate
	 * @param {Effect} root
	 * @param {Effect[]} effects
	 * @param {Effect[]} render_effects
	 */
	#traverse(root, effects, render_effects) {
		root.f ^= CLEAN;

		var effect = root.first;

		while (effect !== null) {
			var flags = effect.f;
			var is_branch = (flags & (BRANCH_EFFECT | ROOT_EFFECT)) !== 0;
			var is_skippable_branch = is_branch && (flags & CLEAN) !== 0;

			var skip = is_skippable_branch || (flags & INERT) !== 0 || this.#skipped_branches.has(effect);

			if (!skip && effect.fn !== null) {
				if (is_branch) {
					effect.f ^= CLEAN;
				} else if ((flags & EFFECT) !== 0) {
					effects.push(effect);
				} else if (is_dirty(effect)) {
					if ((flags & BLOCK_EFFECT) !== 0) this.#maybe_dirty_effects.add(effect);
					update_effect(effect);
				}

				var child = effect.first;

				if (child !== null) {
					effect = child;
					continue;
				}
			}

			while (effect !== null) {
				var next = effect.next;

				if (next !== null) {
					effect = next;
					break;
				}

				effect = effect.parent;
			}
		}
	}

	/**
	 * @param {Effect[]} effects
	 */
	#defer_effects(effects) {
		for (var i = 0; i < effects.length; i += 1) {
			defer_effect(effects[i], this.#dirty_effects, this.#maybe_dirty_effects);
		}
	}

	/**
	 * Associate a change to a given source with the current
	 * batch, noting its previous and current values
	 * @param {Value} source
	 * @param {any} value
	 * @param {boolean} [is_derived]
	 */
	capture(source, value, is_derived = false) {
		if (source.v !== UNINITIALIZED && !this.previous.has(source)) {
			this.previous.set(source, source.v);
		}

		// Don't save errors in `batch_values`, or they won't be thrown in `runtime.js#get`
		if ((source.f & ERROR_VALUE) === 0) {
			this.current.set(source, [value, is_derived]);
			batch_values?.set(source, value);
		}

		if (!this.is_fork) {
			source.v = value;
		}
	}

	activate() {
		current_batch = this;
	}

	deactivate() {
		current_batch = null;
		batch_values = null;
	}

	flush() {
		var source_stacks = DEV ? new Set() : null;

		try {
			is_processing = true;
			current_batch = this;

			this.#process();
		} finally {
			flush_count = 0;
			last_scheduled_effect = null;
			collected_effects = null;
			legacy_updates = null;
			is_processing = false;

			current_batch = null;
			batch_values = null;

			old_values.clear();

			if (DEV) {
				for (const source of /** @type {Set<Source>} */ (source_stacks)) {
					source.updated = null;
				}
			}
		}
	}

	discard() {
		for (const fn of this.#discard_callbacks) fn(this);
		this.#discard_callbacks.clear();

		batches.delete(this);
	}

	/**
	 * @param {Effect} effect
	 */
	register_created_effect(effect) {
		this.#new_effects.push(effect);
	}

	#commit() {
		// If there are other pending batches, they now need to be 'rebased' —
		// in other words, we re-run block/async effects with the newly
		// committed state, unless the batch in question has a more
		// recent value for a given source
		for (const batch of batches) {
			var is_earlier = batch.id < this.id;

			/** @type {Source[]} */
			var sources = [];

			for (const [source, [value, is_derived]] of this.current) {
				if (batch.current.has(source)) {
					var batch_value = /** @type {[any, boolean]} */ (batch.current.get(source))[0]; // faster than destructuring

					if (is_earlier && value !== batch_value) {
						// bring the value up to date
						batch.current.set(source, [value, is_derived]);
					} else {
						// same value or later batch has more recent value,
						// no need to re-run these effects
						continue;
					}
				}

				sources.push(source);
			}

			// Re-run async/block effects that depend on distinct values changed in both batches
			var others = [...batch.current.keys()].filter((s) => !this.current.has(s));

			if (others.length === 0) {
				if (is_earlier) {
					// this batch is now obsolete and can be discarded
					batch.discard();
				}
			} else if (sources.length > 0) {
				if (DEV) {
					invariant(batch.#roots.length === 0, 'Batch has scheduled roots');
				}

				// A batch was unskipped in a later batch -> tell prior batches to unskip it, too
				if (is_earlier) {
					for (const unskipped of this.#unskipped_branches) {
						batch.unskip_effect(unskipped, (e) => {
							if ((e.f & (BLOCK_EFFECT | ASYNC)) !== 0) {
								batch.schedule(e);
							} else {
								batch.#defer_effects([e]);
							}
						});
					}
				}

				batch.activate();

				/** @type {Set<Value>} */
				var marked = new Set();

				/** @type {Map<Reaction, boolean>} */
				var checked = new Map();

				for (var source of sources) {
					mark_effects(source, others, marked, checked);
				}

				checked = new Map();
				var current_unequal = [...batch.current.keys()].filter((c) =>
					this.current.has(c) ? /** @type {[any, boolean]} */ (this.current.get(c))[0] !== c : true
				);

				for (const effect of this.#new_effects) {
					if (
						(effect.f & (DESTROYED | INERT | EAGER_EFFECT)) === 0 &&
						depends_on(effect, current_unequal, checked)
					) {
						if ((effect.f & (ASYNC | BLOCK_EFFECT)) !== 0) {
							set_signal_status(effect, DIRTY);
							batch.schedule(effect);
						} else {
							batch.#dirty_effects.add(effect);
						}
					}
				}

				// Only apply and traverse when we know we triggered async work with marking the effects
				if (batch.#roots.length > 0) {
					batch.apply();

					for (var root of batch.#roots) {
						batch.#traverse(root, [], []);
					}

					batch.#roots = [];
				}

				batch.deactivate();
			}
		}

		for (const batch of batches) {
			if (batch.#blockers.has(this)) {
				batch.#blockers.delete(this);

				if (batch.#blockers.size === 0 && !batch.#is_deferred()) {
					batch.activate();
					batch.#process();
				}
			}
		}
	}

	/**
	 * @param {boolean} blocking
	 * @param {Effect} effect
	 */
	increment(blocking, effect) {
		let pending_count = this.#pending.get(effect) ?? 0;
		this.#pending.set(effect, pending_count + 1);

		if (blocking) {
			let blocking_pending_count = this.#blocking_pending.get(effect) ?? 0;
			this.#blocking_pending.set(effect, blocking_pending_count + 1);
		}
	}

	/**
	 * @param {boolean} blocking
	 * @param {Effect} effect
	 * @param {boolean} skip - whether to skip updates (because this is triggered by a stale reaction)
	 */
	decrement(blocking, effect, skip) {
		let pending_count = this.#pending.get(effect) ?? 0;

		if (pending_count === 1) {
			this.#pending.delete(effect);
		} else {
			this.#pending.set(effect, pending_count - 1);
		}

		if (blocking) {
			let blocking_pending_count = this.#blocking_pending.get(effect) ?? 0;

			if (blocking_pending_count === 1) {
				this.#blocking_pending.delete(effect);
			} else {
				this.#blocking_pending.set(effect, blocking_pending_count - 1);
			}
		}

		if (this.#decrement_queued || skip) return;
		this.#decrement_queued = true;

		queue_micro_task(() => {
			this.#decrement_queued = false;
			this.flush();
		});
	}

	/**
	 * @param {Set<Effect>} dirty_effects
	 * @param {Set<Effect>} maybe_dirty_effects
	 */
	transfer_effects(dirty_effects, maybe_dirty_effects) {
		for (const e of dirty_effects) {
			this.#dirty_effects.add(e);
		}

		for (const e of maybe_dirty_effects) {
			this.#maybe_dirty_effects.add(e);
		}

		dirty_effects.clear();
		maybe_dirty_effects.clear();
	}

	/** @param {(batch: Batch) => void} fn */
	oncommit(fn) {
		this.#commit_callbacks.add(fn);
	}

	/** @param {(batch: Batch) => void} fn */
	ondiscard(fn) {
		this.#discard_callbacks.add(fn);
	}

	settled() {
		return (this.#deferred ??= deferred()).promise;
	}

	static ensure() {
		if (current_batch === null) {
			const batch = (current_batch = new Batch());

			if (!is_processing) {
				batches.add(current_batch);

				if (!is_flushing_sync) {
					queue_micro_task(() => {
						if (current_batch !== batch) {
							// a flushSync happened in the meantime
							return;
						}

						batch.flush();
					});
				}
			}
		}

		return current_batch;
	}

	apply() {
		{
			batch_values = null;
			return;
		}
	}

	/**
	 *
	 * @param {Effect} effect
	 */
	schedule(effect) {
		last_scheduled_effect = effect;

		// defer render effects inside a pending boundary
		// TODO the `REACTION_RAN` check is only necessary because of legacy `$:` effects AFAICT — we can remove later
		if (
			effect.b?.is_pending &&
			(effect.f & (EFFECT | RENDER_EFFECT | MANAGED_EFFECT)) !== 0 &&
			(effect.f & REACTION_RAN) === 0
		) {
			effect.b.defer_effect(effect);
			return;
		}

		var e = effect;

		while (e.parent !== null) {
			e = e.parent;
			var flags = e.f;

			// if the effect is being scheduled because a parent (each/await/etc) block
			// updated an internal source, or because a branch is being unskipped,
			// bail out or we'll cause a second flush
			if (collected_effects !== null && e === active_effect) {

				// in sync mode, render effects run during traversal. in an extreme edge case
				// — namely that we're setting a value inside a derived read during traversal —
				// they can be made dirty after they have already been visited, in which
				// case we shouldn't bail out. we also shouldn't bail out if we're
				// updating a store inside a `$:`, since this might invalidate
				// effects that were already visited
				if (
					(active_reaction === null || (active_reaction.f & DERIVED) === 0) &&
					!legacy_is_updating_store
				) {
					return;
				}
			}

			if ((flags & (ROOT_EFFECT | BRANCH_EFFECT)) !== 0) {
				if ((flags & CLEAN) === 0) {
					// branch is already dirty, bail
					return;
				}

				e.f ^= CLEAN;
			}
		}

		this.#roots.push(e);
	}
}

// TODO Svelte@6 think about removing the callback argument.
/**
 * Synchronously flush any pending updates.
 * Returns void if no callback is provided, otherwise returns the result of calling the callback.
 * @template [T=void]
 * @param {(() => T) | undefined} [fn]
 * @returns {T}
 */
function flushSync(fn) {
	var was_flushing_sync = is_flushing_sync;
	is_flushing_sync = true;

	try {
		var result;

		if (fn) {
			if (current_batch !== null && !current_batch.is_fork) {
				current_batch.flush();
			}

			result = fn();
		}

		while (true) {
			flush_tasks();

			if (current_batch === null) {
				return /** @type {T} */ (result);
			}

			current_batch.flush();
		}
	} finally {
		is_flushing_sync = was_flushing_sync;
	}
}

function infinite_loop_guard() {
	if (DEV) {
		var updates = new Map();

		for (const source of /** @type {Batch} */ (current_batch).current.keys()) {
			for (const [stack, update] of source.updated ?? []) {
				var entry = updates.get(stack);

				if (!entry) {
					entry = { error: update.error, count: 0 };
					updates.set(stack, entry);
				}

				entry.count += update.count;
			}
		}

		for (const update of updates.values()) {
			if (update.error) {
				// eslint-disable-next-line no-console
				console.error(update.error);
			}
		}
	}

	try {
		effect_update_depth_exceeded();
	} catch (error) {
		if (DEV) {
			// stack contains no useful information, replace it
			define_property(error, 'stack', { value: '' });
		}

		// Best effort: invoke the boundary nearest the most recent
		// effect and hope that it's relevant to the infinite loop
		invoke_error_boundary(error, last_scheduled_effect);
	}
}

/** @type {Set<Effect> | null} */
let eager_block_effects = null;

/**
 * @param {Array<Effect>} effects
 * @returns {void}
 */
function flush_queued_effects(effects) {
	var length = effects.length;
	if (length === 0) return;

	var i = 0;

	while (i < length) {
		var effect = effects[i++];

		if ((effect.f & (DESTROYED | INERT)) === 0 && is_dirty(effect)) {
			eager_block_effects = new Set();

			update_effect(effect);

			// Effects with no dependencies or teardown do not get added to the effect tree.
			// Deferred effects (e.g. `$effect(...)`) _are_ added to the tree because we
			// don't know if we need to keep them until they are executed. Doing the check
			// here (rather than in `update_effect`) allows us to skip the work for
			// immediate effects.
			if (
				effect.deps === null &&
				effect.first === null &&
				effect.nodes === null &&
				effect.teardown === null &&
				effect.ac === null
			) {
				// remove this effect from the graph
				unlink_effect(effect);
			}

			// If update_effect() has a flushSync() in it, we may have flushed another flush_queued_effects(),
			// which already handled this logic and did set eager_block_effects to null.
			if (eager_block_effects?.size > 0) {
				old_values.clear();

				for (const e of eager_block_effects) {
					// Skip eager effects that have already been unmounted
					if ((e.f & (DESTROYED | INERT)) !== 0) continue;

					// Run effects in order from ancestor to descendant, else we could run into nullpointers
					/** @type {Effect[]} */
					const ordered_effects = [e];
					let ancestor = e.parent;
					while (ancestor !== null) {
						if (eager_block_effects.has(ancestor)) {
							eager_block_effects.delete(ancestor);
							ordered_effects.push(ancestor);
						}
						ancestor = ancestor.parent;
					}

					for (let j = ordered_effects.length - 1; j >= 0; j--) {
						const e = ordered_effects[j];
						// Skip eager effects that have already been unmounted
						if ((e.f & (DESTROYED | INERT)) !== 0) continue;
						update_effect(e);
					}
				}

				eager_block_effects.clear();
			}
		}
	}

	eager_block_effects = null;
}

/**
 * This is similar to `mark_reactions`, but it only marks async/block effects
 * depending on `value` and at least one of the other `sources`, so that
 * these effects can re-run after another batch has been committed
 * @param {Value} value
 * @param {Source[]} sources
 * @param {Set<Value>} marked
 * @param {Map<Reaction, boolean>} checked
 */
function mark_effects(value, sources, marked, checked) {
	if (marked.has(value)) return;
	marked.add(value);

	if (value.reactions !== null) {
		for (const reaction of value.reactions) {
			const flags = reaction.f;

			if ((flags & DERIVED) !== 0) {
				mark_effects(/** @type {Derived} */ (reaction), sources, marked, checked);
			} else if (
				(flags & (ASYNC | BLOCK_EFFECT)) !== 0 &&
				(flags & DIRTY) === 0 &&
				depends_on(reaction, sources, checked)
			) {
				set_signal_status(reaction, DIRTY);
				schedule_effect(/** @type {Effect} */ (reaction));
			}
		}
	}
}

/**
 * @param {Reaction} reaction
 * @param {Source[]} sources
 * @param {Map<Reaction, boolean>} checked
 */
function depends_on(reaction, sources, checked) {
	const depends = checked.get(reaction);
	if (depends !== undefined) return depends;

	if (reaction.deps !== null) {
		for (const dep of reaction.deps) {
			if (includes.call(sources, dep)) {
				return true;
			}

			if ((dep.f & DERIVED) !== 0 && depends_on(/** @type {Derived} */ (dep), sources, checked)) {
				checked.set(/** @type {Derived} */ (dep), true);
				return true;
			}
		}
	}

	checked.set(reaction, false);

	return false;
}

/**
 * @param {Effect} effect
 * @returns {void}
 */
function schedule_effect(effect) {
	/** @type {Batch} */ (current_batch).schedule(effect);
}

/**
 * Mark all the effects inside a skipped branch CLEAN, so that
 * they can be correctly rescheduled later. Tracks dirty and maybe_dirty
 * effects so they can be rescheduled if the branch survives.
 * @param {Effect} effect
 * @param {{ d: Effect[], m: Effect[] }} tracked
 */
function reset_branch(effect, tracked) {
	// clean branch = nothing dirty inside, no need to traverse further
	if ((effect.f & BRANCH_EFFECT) !== 0 && (effect.f & CLEAN) !== 0) {
		return;
	}

	if ((effect.f & DIRTY) !== 0) {
		tracked.d.push(effect);
	} else if ((effect.f & MAYBE_DIRTY) !== 0) {
		tracked.m.push(effect);
	}

	set_signal_status(effect, CLEAN);

	var e = effect.first;
	while (e !== null) {
		reset_branch(e, tracked);
		e = e.next;
	}
}

/**
 * Mark an entire effect tree clean following an error
 * @param {Effect} effect
 */
function reset_all(effect) {
	set_signal_status(effect, CLEAN);

	var e = effect.first;
	while (e !== null) {
		reset_all(e);
		e = e.next;
	}
}

/**
 * Returns a `subscribe` function that integrates external event-based systems with Svelte's reactivity.
 * It's particularly useful for integrating with web APIs like `MediaQuery`, `IntersectionObserver`, or `WebSocket`.
 *
 * If `subscribe` is called inside an effect (including indirectly, for example inside a getter),
 * the `start` callback will be called with an `update` function. Whenever `update` is called, the effect re-runs.
 *
 * If `start` returns a cleanup function, it will be called when the effect is destroyed.
 *
 * If `subscribe` is called in multiple effects, `start` will only be called once as long as the effects
 * are active, and the returned teardown function will only be called when all effects are destroyed.
 *
 * It's best understood with an example. Here's an implementation of [`MediaQuery`](https://svelte.dev/docs/svelte/svelte-reactivity#MediaQuery):
 *
 * ```js
 * import { createSubscriber } from 'svelte/reactivity';
 * import { on } from 'svelte/events';
 *
 * export class MediaQuery {
 * 	#query;
 * 	#subscribe;
 *
 * 	constructor(query) {
 * 		this.#query = window.matchMedia(`(${query})`);
 *
 * 		this.#subscribe = createSubscriber((update) => {
 * 			// when the `change` event occurs, re-run any effects that read `this.current`
 * 			const off = on(this.#query, 'change', update);
 *
 * 			// stop listening when all the effects are destroyed
 * 			return () => off();
 * 		});
 * 	}
 *
 * 	get current() {
 * 		// This makes the getter reactive, if read in an effect
 * 		this.#subscribe();
 *
 * 		// Return the current state of the query, whether or not we're in an effect
 * 		return this.#query.matches;
 * 	}
 * }
 * ```
 * @param {(update: () => void) => (() => void) | void} start
 * @since 5.7.0
 */
function createSubscriber(start) {
	let subscribers = 0;
	let version = source(0);
	/** @type {(() => void) | void} */
	let stop;

	if (DEV) {
		tag(version, 'createSubscriber version');
	}

	return () => {
		if (effect_tracking()) {
			get(version);

			render_effect(() => {
				if (subscribers === 0) {
					stop = untrack(() => start(() => increment(version)));
				}

				subscribers += 1;

				return () => {
					queue_micro_task(() => {
						// Only count down after a microtask, else we would reach 0 before our own render effect reruns,
						// but reach 1 again when the tick callback of the prior teardown runs. That would mean we
						// re-subcribe unnecessarily and create a memory leak because the old subscription is never cleaned up.
						subscribers -= 1;

						if (subscribers === 0) {
							stop?.();
							stop = undefined;
							// Increment the version to ensure any dependent deriveds are marked dirty when the subscription is picked up again later.
							// If we didn't do this then the comparison of write versions would determine that the derived has a later version than
							// the subscriber, and it would not be re-run.
							increment(version);
						}
					});
				};
			});
		}
	};
}

/** @import { Effect, Source, TemplateNode, } from '#client' */

/**
 * @typedef {{
 * 	 onerror?: (error: unknown, reset: () => void) => void;
 *   failed?: (anchor: Node, error: () => unknown, reset: () => () => void) => void;
 *   pending?: (anchor: Node) => void;
 * }} BoundaryProps
 */

var flags = EFFECT_TRANSPARENT | EFFECT_PRESERVED;

/**
 * @param {TemplateNode} node
 * @param {BoundaryProps} props
 * @param {((anchor: Node) => void)} children
 * @param {((error: unknown) => unknown) | undefined} [transform_error]
 * @returns {void}
 */
function boundary(node, props, children, transform_error) {
	new Boundary(node, props, children, transform_error);
}

class Boundary {
	/** @type {Boundary | null} */
	parent;

	is_pending = false;

	/**
	 * API-level transformError transform function. Transforms errors before they reach the `failed` snippet.
	 * Inherited from parent boundary, or defaults to identity.
	 * @type {(error: unknown) => unknown}
	 */
	transform_error;

	/** @type {TemplateNode} */
	#anchor;

	/** @type {TemplateNode | null} */
	#hydrate_open = null;

	/** @type {BoundaryProps} */
	#props;

	/** @type {((anchor: Node) => void)} */
	#children;

	/** @type {Effect} */
	#effect;

	/** @type {Effect | null} */
	#main_effect = null;

	/** @type {Effect | null} */
	#pending_effect = null;

	/** @type {Effect | null} */
	#failed_effect = null;

	/** @type {DocumentFragment | null} */
	#offscreen_fragment = null;

	#local_pending_count = 0;
	#pending_count = 0;
	#pending_count_update_queued = false;

	/** @type {Set<Effect>} */
	#dirty_effects = new Set();

	/** @type {Set<Effect>} */
	#maybe_dirty_effects = new Set();

	/**
	 * A source containing the number of pending async deriveds/expressions.
	 * Only created if `$effect.pending()` is used inside the boundary,
	 * otherwise updating the source results in needless `Batch.ensure()`
	 * calls followed by no-op flushes
	 * @type {Source<number> | null}
	 */
	#effect_pending = null;

	#effect_pending_subscriber = createSubscriber(() => {
		this.#effect_pending = source(this.#local_pending_count);

		if (DEV) {
			tag(this.#effect_pending, '$effect.pending()');
		}

		return () => {
			this.#effect_pending = null;
		};
	});

	/**
	 * @param {TemplateNode} node
	 * @param {BoundaryProps} props
	 * @param {((anchor: Node) => void)} children
	 * @param {((error: unknown) => unknown) | undefined} [transform_error]
	 */
	constructor(node, props, children, transform_error) {
		this.#anchor = node;
		this.#props = props;

		this.#children = (anchor) => {
			var effect = /** @type {Effect} */ (active_effect);

			effect.b = this;
			effect.f |= BOUNDARY_EFFECT;

			children(anchor);
		};

		this.parent = /** @type {Effect} */ (active_effect).b;

		// Inherit transform_error from parent boundary, or use the provided one, or default to identity
		this.transform_error = transform_error ?? this.parent?.transform_error ?? ((e) => e);

		this.#effect = block(() => {
			{
				this.#render();
			}
		}, flags);
	}

	#hydrate_resolved_content() {
		try {
			this.#main_effect = branch(() => this.#children(this.#anchor));
		} catch (error) {
			this.error(error);
		}
	}

	/**
	 * @param {unknown} error The deserialized error from the server's hydration comment
	 */
	#hydrate_failed_content(error) {
		const failed = this.#props.failed;
		if (!failed) return;

		this.#failed_effect = branch(() => {
			failed(
				this.#anchor,
				() => error,
				() => () => {}
			);
		});
	}

	#hydrate_pending_content() {
		const pending = this.#props.pending;
		if (!pending) return;

		this.is_pending = true;
		this.#pending_effect = branch(() => pending(this.#anchor));

		queue_micro_task(() => {
			var fragment = (this.#offscreen_fragment = document.createDocumentFragment());
			var anchor = create_text();

			fragment.append(anchor);

			this.#main_effect = this.#run(() => {
				return branch(() => this.#children(anchor));
			});

			if (this.#pending_count === 0) {
				this.#anchor.before(fragment);
				this.#offscreen_fragment = null;

				pause_effect(/** @type {Effect} */ (this.#pending_effect), () => {
					this.#pending_effect = null;
				});

				this.#resolve(/** @type {Batch} */ (current_batch));
			}
		});
	}

	#render() {
		try {
			this.is_pending = this.has_pending_snippet();
			this.#pending_count = 0;
			this.#local_pending_count = 0;

			this.#main_effect = branch(() => {
				this.#children(this.#anchor);
			});

			if (this.#pending_count > 0) {
				var fragment = (this.#offscreen_fragment = document.createDocumentFragment());
				move_effect(this.#main_effect, fragment);

				const pending = /** @type {(anchor: Node) => void} */ (this.#props.pending);
				this.#pending_effect = branch(() => pending(this.#anchor));
			} else {
				this.#resolve(/** @type {Batch} */ (current_batch));
			}
		} catch (error) {
			this.error(error);
		}
	}

	/**
	 * @param {Batch} batch
	 */
	#resolve(batch) {
		this.is_pending = false;

		// any effects that were previously deferred should be transferred
		// to the batch, which will flush in the next microtask
		batch.transfer_effects(this.#dirty_effects, this.#maybe_dirty_effects);
	}

	/**
	 * Defer an effect inside a pending boundary until the boundary resolves
	 * @param {Effect} effect
	 */
	defer_effect(effect) {
		defer_effect(effect, this.#dirty_effects, this.#maybe_dirty_effects);
	}

	/**
	 * Returns `false` if the effect exists inside a boundary whose pending snippet is shown
	 * @returns {boolean}
	 */
	is_rendered() {
		return !this.is_pending && (!this.parent || this.parent.is_rendered());
	}

	has_pending_snippet() {
		return !!this.#props.pending;
	}

	/**
	 * @template T
	 * @param {() => T} fn
	 */
	#run(fn) {
		var previous_effect = active_effect;
		var previous_reaction = active_reaction;
		var previous_ctx = component_context;

		set_active_effect(this.#effect);
		set_active_reaction(this.#effect);
		set_component_context(this.#effect.ctx);

		try {
			Batch.ensure();
			return fn();
		} catch (e) {
			handle_error(e);
			return null;
		} finally {
			set_active_effect(previous_effect);
			set_active_reaction(previous_reaction);
			set_component_context(previous_ctx);
		}
	}

	/**
	 * Updates the pending count associated with the currently visible pending snippet,
	 * if any, such that we can replace the snippet with content once work is done
	 * @param {1 | -1} d
	 * @param {Batch} batch
	 */
	#update_pending_count(d, batch) {
		if (!this.has_pending_snippet()) {
			if (this.parent) {
				this.parent.#update_pending_count(d, batch);
			}

			// if there's no parent, we're in a scope with no pending snippet
			return;
		}

		this.#pending_count += d;

		if (this.#pending_count === 0) {
			this.#resolve(batch);

			if (this.#pending_effect) {
				pause_effect(this.#pending_effect, () => {
					this.#pending_effect = null;
				});
			}

			if (this.#offscreen_fragment) {
				this.#anchor.before(this.#offscreen_fragment);
				this.#offscreen_fragment = null;
			}
		}
	}

	/**
	 * Update the source that powers `$effect.pending()` inside this boundary,
	 * and controls when the current `pending` snippet (if any) is removed.
	 * Do not call from inside the class
	 * @param {1 | -1} d
	 * @param {Batch} batch
	 */
	update_pending_count(d, batch) {
		this.#update_pending_count(d, batch);

		this.#local_pending_count += d;

		if (!this.#effect_pending || this.#pending_count_update_queued) return;
		this.#pending_count_update_queued = true;

		queue_micro_task(() => {
			this.#pending_count_update_queued = false;
			if (this.#effect_pending) {
				internal_set(this.#effect_pending, this.#local_pending_count);
			}
		});
	}

	get_effect_pending() {
		this.#effect_pending_subscriber();
		return get(/** @type {Source<number>} */ (this.#effect_pending));
	}

	/** @param {unknown} error */
	error(error) {
		var onerror = this.#props.onerror;
		let failed = this.#props.failed;

		// If we have nothing to capture the error, or if we hit an error while
		// rendering the fallback, re-throw for another boundary to handle
		if (!onerror && !failed) {
			throw error;
		}

		if (this.#main_effect) {
			destroy_effect(this.#main_effect);
			this.#main_effect = null;
		}

		if (this.#pending_effect) {
			destroy_effect(this.#pending_effect);
			this.#pending_effect = null;
		}

		if (this.#failed_effect) {
			destroy_effect(this.#failed_effect);
			this.#failed_effect = null;
		}

		var did_reset = false;
		var calling_on_error = false;

		const reset = () => {
			if (did_reset) {
				svelte_boundary_reset_noop();
				return;
			}

			did_reset = true;

			if (calling_on_error) {
				svelte_boundary_reset_onerror();
			}

			if (this.#failed_effect !== null) {
				pause_effect(this.#failed_effect, () => {
					this.#failed_effect = null;
				});
			}

			this.#run(() => {
				this.#render();
			});
		};

		/** @param {unknown} transformed_error */
		const handle_error_result = (transformed_error) => {
			try {
				calling_on_error = true;
				onerror?.(transformed_error, reset);
				calling_on_error = false;
			} catch (error) {
				invoke_error_boundary(error, this.#effect && this.#effect.parent);
			}

			if (failed) {
				this.#failed_effect = this.#run(() => {
					try {
						return branch(() => {
							// errors in `failed` snippets cause the boundary to error again
							// TODO Svelte 6: revisit this decision, most likely better to go to parent boundary instead
							var effect = /** @type {Effect} */ (active_effect);

							effect.b = this;
							effect.f |= BOUNDARY_EFFECT;

							failed(
								this.#anchor,
								() => transformed_error,
								() => reset
							);
						});
					} catch (error) {
						invoke_error_boundary(error, /** @type {Effect} */ (this.#effect.parent));
						return null;
					}
				});
			}
		};

		queue_micro_task(() => {
			// Run the error through the API-level transformError transform (e.g. SvelteKit's handleError)
			/** @type {unknown} */
			var result;
			try {
				result = this.transform_error(error);
			} catch (e) {
				invoke_error_boundary(e, this.#effect && this.#effect.parent);
				return;
			}

			if (
				result !== null &&
				typeof result === 'object' &&
				typeof (/** @type {any} */ (result).then) === 'function'
			) {
				// transformError returned a Promise — wait for it
				/** @type {any} */ (result).then(
					handle_error_result,
					/** @param {unknown} e */
					(e) => invoke_error_boundary(e, this.#effect && this.#effect.parent)
				);
			} else {
				// Synchronous result — handle immediately
				handle_error_result(result);
			}
		});
	}
}

/** @import { Blocker, Effect, Value } from '#client' */

/**
 * @param {Blocker[]} blockers
 * @param {Array<() => any>} sync
 * @param {Array<() => Promise<any>>} async
 * @param {(values: Value[]) => any} fn
 */
function flatten(blockers, sync, async, fn) {
	const d = is_runes() ? derived : derived_safe_equal;

	// Filter out already-settled blockers - no need to wait for them
	var pending = blockers.filter((b) => !b.settled);

	if (async.length === 0 && pending.length === 0) {
		fn(sync.map(d));
		return;
	}

	var parent = /** @type {Effect} */ (active_effect);

	var restore = capture();
	var blocker_promise =
		pending.length === 1
			? pending[0].promise
			: pending.length > 1
				? Promise.all(pending.map((b) => b.promise))
				: null;

	/** @param {Value[]} values */
	function finish(values) {
		restore();

		try {
			fn(values);
		} catch (error) {
			if ((parent.f & DESTROYED) === 0) {
				invoke_error_boundary(error, parent);
			}
		}

		unset_context();
	}

	// Fast path: blockers but no async expressions
	if (async.length === 0) {
		/** @type {Promise<any>} */ (blocker_promise).then(() => finish(sync.map(d)));
		return;
	}

	var decrement_pending = increment_pending();

	// Full path: has async expressions
	function run() {
		Promise.all(async.map((expression) => async_derived(expression)))
			.then((result) => finish([...sync.map(d), ...result]))
			.catch((error) => invoke_error_boundary(error, parent))
			.finally(() => decrement_pending());
	}

	if (blocker_promise) {
		blocker_promise.then(() => {
			restore();
			run();
			unset_context();
		});
	} else {
		run();
	}
}

/**
 * Captures the current effect context so that we can restore it after
 * some asynchronous work has happened (so that e.g. `await a + b`
 * causes `b` to be registered as a dependency).
 */
function capture() {
	var previous_effect = /** @type {Effect} */ (active_effect);
	var previous_reaction = active_reaction;
	var previous_component_context = component_context;
	var previous_batch = /** @type {Batch} */ (current_batch);

	if (DEV) {
		var previous_dev_stack = dev_stack;
	}

	return function restore(activate_batch = true) {
		set_active_effect(previous_effect);
		set_active_reaction(previous_reaction);
		set_component_context(previous_component_context);

		if (activate_batch && (previous_effect.f & DESTROYED) === 0) {
			// TODO we only need optional chaining here because `{#await ...}` blocks
			// are anomalous. Once we retire them we can get rid of it
			previous_batch?.activate();
			previous_batch?.apply();
		}

		if (DEV) {
			set_reactivity_loss_tracker(null);
			set_dev_stack(previous_dev_stack);
		}
	};
}

/**
 * Reset `current_async_effect` after the `promise` resolves, so
 * that we can emit `await_reactivity_loss` warnings
 * @template T
 * @param {Promise<T>} promise
 * @returns {Promise<() => T>}
 */
async function track_reactivity_loss(promise) {
	var previous_async_effect = reactivity_loss_tracker;
	var value = await promise;

	return () => {
		set_reactivity_loss_tracker(previous_async_effect);
		return value;
	};
}

function unset_context(deactivate_batch = true) {
	set_active_effect(null);
	set_active_reaction(null);
	set_component_context(null);
	if (deactivate_batch) current_batch?.deactivate();

	if (DEV) {
		set_reactivity_loss_tracker(null);
		set_dev_stack(null);
	}
}

/**
 * @returns {(skip?: boolean) => void}
 */
function increment_pending() {
	var effect = /** @type {Effect} */ (active_effect);
	var boundary = /** @type {Boundary} */ (effect.b);
	var batch = /** @type {Batch} */ (current_batch);
	var blocking = boundary.is_rendered();

	boundary.update_pending_count(1, batch);
	batch.increment(blocking, effect);

	return (skip = false) => {
		boundary.update_pending_count(-1, batch);
		batch.decrement(blocking, effect, skip);
	};
}

/** @import { Derived, Effect, Source } from '#client' */
/** @import { Batch } from './batch.js'; */
/** @import { Boundary } from '../dom/blocks/boundary.js'; */

/**
 * This allows us to track 'reactivity loss' that occurs when signals
 * are read after a non-context-restoring `await`. Dev-only
 * @type {{ effect: Effect, warned: boolean } | null}
 */
let reactivity_loss_tracker = null;

/** @param {{ effect: Effect, warned: boolean } | null} v */
function set_reactivity_loss_tracker(v) {
	reactivity_loss_tracker = v;
}

const recent_async_deriveds = new Set();

/**
 * @template V
 * @param {() => V} fn
 * @returns {Derived<V>}
 */
/*#__NO_SIDE_EFFECTS__*/
function derived(fn) {
	var flags = DERIVED | DIRTY;
	var parent_derived =
		active_reaction !== null && (active_reaction.f & DERIVED) !== 0
			? /** @type {Derived} */ (active_reaction)
			: null;

	if (active_effect !== null) {
		// Since deriveds are evaluated lazily, any effects created inside them are
		// created too late to ensure that the parent effect is added to the tree
		active_effect.f |= EFFECT_PRESERVED;
	}

	/** @type {Derived<V>} */
	const signal = {
		ctx: component_context,
		deps: null,
		effects: null,
		equals: equals$1,
		f: flags,
		fn,
		reactions: null,
		rv: 0,
		v: /** @type {V} */ (UNINITIALIZED),
		wv: 0,
		parent: parent_derived ?? active_effect,
		ac: null
	};

	if (DEV && tracing_mode_flag) {
		signal.created = get_error('created at');
	}

	return signal;
}

/**
 * @template V
 * @param {() => V | Promise<V>} fn
 * @param {string} [label]
 * @param {string} [location] If provided, print a warning if the value is not read immediately after update
 * @returns {Promise<Source<V>>}
 */
/*#__NO_SIDE_EFFECTS__*/
function async_derived(fn, label, location) {
	let parent = /** @type {Effect | null} */ (active_effect);

	if (parent === null) {
		async_derived_orphan();
	}

	var promise = /** @type {Promise<V>} */ (/** @type {unknown} */ (undefined));
	var signal = source(/** @type {V} */ (UNINITIALIZED));

	if (DEV) signal.label = label;

	// only suspend in async deriveds created on initialisation
	var should_suspend = !active_reaction;

	/** @type {Map<Batch, ReturnType<typeof deferred<V>>>} */
	var deferreds = new Map();

	async_effect(() => {
		if (DEV) {
			reactivity_loss_tracker = {
				effect: /** @type {Effect} */ (active_effect),
				warned: false
			};
		}

		var effect = /** @type {Effect} */ (active_effect);

		/** @type {ReturnType<typeof deferred<V>>} */
		var d = deferred();
		promise = d.promise;

		try {
			// If this code is changed at some point, make sure to still access the then property
			// of fn() to read any signals it might access, so that we track them as dependencies.
			// We call `unset_context` to undo any `save` calls that happen inside `fn()`
			Promise.resolve(fn()).then(d.resolve, d.reject).finally(unset_context);
		} catch (error) {
			d.reject(error);
			unset_context();
		}

		if (DEV) {
			reactivity_loss_tracker = null;
		}

		var batch = /** @type {Batch} */ (current_batch);

		if (should_suspend) {
			// we only increment the batch's pending state for updates, not creation, otherwise
			// we will decrement to zero before the work that depends on this promise (e.g. a
			// template effect) has initialized, causing the batch to resolve prematurely
			if ((effect.f & REACTION_RAN) !== 0) {
				var decrement_pending = increment_pending();
			}

			if (/** @type {Boundary} */ (parent.b).is_rendered()) {
				deferreds.get(batch)?.reject(STALE_REACTION);
				deferreds.delete(batch); // delete to ensure correct order in Map iteration below
			} else {
				// While the boundary is still showing pending, a new run supersedes all older in-flight runs
				// for this async expression. Cancel eagerly so resolution cannot commit stale values.
				for (const d of deferreds.values()) {
					d.reject(STALE_REACTION);
				}
				deferreds.clear();
			}

			deferreds.set(batch, d);
		}

		/**
		 * @param {any} value
		 * @param {unknown} error
		 */
		const handler = (value, error = undefined) => {
			if (DEV) {
				reactivity_loss_tracker = null;
			}

			if (decrement_pending) {
				// don't trigger an update if we're only here because
				// the promise was superseded before it could resolve
				var skip = error === STALE_REACTION;
				decrement_pending(skip);
			}

			if (error === STALE_REACTION || (effect.f & DESTROYED) !== 0) {
				return;
			}

			batch.activate();

			if (error) {
				signal.f |= ERROR_VALUE;

				// @ts-expect-error the error is the wrong type, but we don't care
				internal_set(signal, error);
			} else {
				if ((signal.f & ERROR_VALUE) !== 0) {
					signal.f ^= ERROR_VALUE;
				}

				internal_set(signal, value);

				// All prior async derived runs are now stale
				for (const [b, d] of deferreds) {
					deferreds.delete(b);
					if (b === batch) break;
					d.reject(STALE_REACTION);
				}

				if (DEV && location !== undefined) {
					recent_async_deriveds.add(signal);

					setTimeout(() => {
						if (recent_async_deriveds.has(signal)) {
							await_waterfall(/** @type {string} */ (signal.label), location);
							recent_async_deriveds.delete(signal);
						}
					});
				}
			}

			batch.deactivate();
		};

		d.promise.then(handler, (e) => handler(null, e || 'unknown'));
	});

	teardown(() => {
		for (const d of deferreds.values()) {
			d.reject(STALE_REACTION);
		}
	});

	if (DEV) {
		// add a flag that lets this be printed as a derived
		// when using `$inspect.trace()`
		signal.f |= ASYNC;
	}

	return new Promise((fulfil) => {
		/** @param {Promise<V>} p */
		function next(p) {
			function go() {
				if (p === promise) {
					fulfil(signal);
				} else {
					// if the effect re-runs before the initial promise
					// resolves, delay resolution until we have a value
					next(promise);
				}
			}

			p.then(go, go);
		}

		next(promise);
	});
}

/**
 * @template V
 * @param {() => V} fn
 * @returns {Derived<V>}
 */
/*#__NO_SIDE_EFFECTS__*/
function user_derived(fn) {
	const d = derived(fn);

	push_reaction_value(d);

	return d;
}

/**
 * @template V
 * @param {() => V} fn
 * @returns {Derived<V>}
 */
/*#__NO_SIDE_EFFECTS__*/
function derived_safe_equal(fn) {
	const signal = derived(fn);
	signal.equals = safe_equals;
	return signal;
}

/**
 * @param {Derived} derived
 * @returns {void}
 */
function destroy_derived_effects(derived) {
	var effects = derived.effects;

	if (effects !== null) {
		derived.effects = null;

		for (var i = 0; i < effects.length; i += 1) {
			destroy_effect(/** @type {Effect} */ (effects[i]));
		}
	}
}

/**
 * The currently updating deriveds, used to detect infinite recursion
 * in dev mode and provide a nicer error than 'too much recursion'
 * @type {Derived[]}
 */
let stack = [];

/**
 * @param {Derived} derived
 * @returns {Effect | null}
 */
function get_derived_parent_effect(derived) {
	var parent = derived.parent;
	while (parent !== null) {
		if ((parent.f & DERIVED) === 0) {
			// The original parent effect might've been destroyed but the derived
			// is used elsewhere now - do not return the destroyed effect in that case
			return (parent.f & DESTROYED) === 0 ? /** @type {Effect} */ (parent) : null;
		}
		parent = parent.parent;
	}
	return null;
}

/**
 * @template T
 * @param {Derived} derived
 * @returns {T}
 */
function execute_derived(derived) {
	var value;
	var prev_active_effect = active_effect;

	set_active_effect(get_derived_parent_effect(derived));

	if (DEV) {
		let prev_eager_effects = eager_effects;
		set_eager_effects(new Set());
		try {
			if (includes.call(stack, derived)) {
				derived_references_self();
			}

			stack.push(derived);

			derived.f &= ~WAS_MARKED;
			destroy_derived_effects(derived);
			value = update_reaction(derived);
		} finally {
			set_active_effect(prev_active_effect);
			set_eager_effects(prev_eager_effects);
			stack.pop();
		}
	} else {
		try {
			derived.f &= ~WAS_MARKED;
			destroy_derived_effects(derived);
			value = update_reaction(derived);
		} finally {
			set_active_effect(prev_active_effect);
		}
	}

	return value;
}

/**
 * @param {Derived} derived
 * @returns {void}
 */
function update_derived(derived) {
	var value = execute_derived(derived);

	if (!derived.equals(value)) {
		derived.wv = increment_write_version();

		// in a fork, we don't update the underlying value, just `batch_values`.
		// the underlying value will be updated when the fork is committed.
		// otherwise, the next time we get here after a 'real world' state
		// change, `derived.equals` may incorrectly return `true`
		if (!current_batch?.is_fork || derived.deps === null) {
			if (current_batch !== null) {
				current_batch.capture(derived, value, true);
			} else {
				derived.v = value;
			}

			// deriveds without dependencies should never be recomputed
			if (derived.deps === null) {
				set_signal_status(derived, CLEAN);
				return;
			}
		}
	}

	// don't mark derived clean if we're reading it inside a
	// cleanup function, or it will cache a stale value
	if (is_destroying_effect) {
		return;
	}

	// During time traveling we don't want to reset the status so that
	// traversal of the graph in the other batches still happens
	if (batch_values !== null) {
		// only cache the value if we're in a tracking context, otherwise we won't
		// clear the cache in `mark_reactions` when dependencies are updated
		if (effect_tracking() || current_batch?.is_fork) {
			batch_values.set(derived, value);
		}
	} else {
		update_derived_status(derived);
	}
}

/**
 * @param {Derived} derived
 */
function freeze_derived_effects(derived) {
	if (derived.effects === null) return;

	for (const e of derived.effects) {
		// if the effect has a teardown function or abort signal, call it
		if (e.teardown || e.ac) {
			e.teardown?.();
			e.ac?.abort(STALE_REACTION);

			// make it a noop so it doesn't get called again if the derived
			// is unfrozen. we don't set it to `null`, because the existence
			// of a teardown function is what determines whether the
			// effect runs again during unfreezing
			e.teardown = noop;
			e.ac = null;

			remove_reactions(e, 0);
			destroy_effect_children(e);
		}
	}
}

/**
 * @param {Derived} derived
 */
function unfreeze_derived_effects(derived) {
	if (derived.effects === null) return;

	for (const e of derived.effects) {
		// if the effect was previously frozen — indicated by the presence
		// of a teardown function — unfreeze it
		if (e.teardown) {
			update_effect(e);
		}
	}
}

/** @import { Derived, Effect, Source, Value } from '#client' */

/** @type {Set<any>} */
let eager_effects = new Set();

/** @type {Map<Source, any>} */
const old_values = new Map();

/**
 * @param {Set<any>} v
 */
function set_eager_effects(v) {
	eager_effects = v;
}

let eager_effects_deferred = false;

function set_eager_effects_deferred() {
	eager_effects_deferred = true;
}

/**
 * @template V
 * @param {V} v
 * @param {Error | null} [stack]
 * @returns {Source<V>}
 */
// TODO rename this to `state` throughout the codebase
function source(v, stack) {
	/** @type {Value} */
	var signal = {
		f: 0, // TODO ideally we could skip this altogether, but it causes type errors
		v,
		reactions: null,
		equals: equals$1,
		rv: 0,
		wv: 0
	};

	if (DEV && tracing_mode_flag) {
		signal.created = stack ?? get_error('created at');
		signal.updated = null;
		signal.set_during_effect = false;
		signal.trace = null;
	}

	return signal;
}

/**
 * @template V
 * @param {V} v
 * @param {Error | null} [stack]
 */
/*#__NO_SIDE_EFFECTS__*/
function state(v, stack) {
	const s = source(v, stack);

	push_reaction_value(s);

	return s;
}

/**
 * @template V
 * @param {V} initial_value
 * @param {boolean} [immutable]
 * @returns {Source<V>}
 */
/*#__NO_SIDE_EFFECTS__*/
function mutable_source(initial_value, immutable = false, trackable = true) {
	const s = source(initial_value);
	if (!immutable) {
		s.equals = safe_equals;
	}

	// bind the signal to the component context, in case we need to
	// track updates to trigger beforeUpdate/afterUpdate callbacks
	if (legacy_mode_flag && trackable && component_context !== null && component_context.l !== null) {
		(component_context.l.s ??= []).push(s);
	}

	return s;
}

/**
 * @template V
 * @param {Source<V>} source
 * @param {V} value
 * @param {boolean} [should_proxy]
 * @returns {V}
 */
function set(source, value, should_proxy = false) {
	if (
		active_reaction !== null &&
		// since we are untracking the function inside `$inspect.with` we need to add this check
		// to ensure we error if state is set inside an inspect effect
		(!untracking || (active_reaction.f & EAGER_EFFECT) !== 0) &&
		is_runes() &&
		(active_reaction.f & (DERIVED | BLOCK_EFFECT | ASYNC | EAGER_EFFECT)) !== 0 &&
		(current_sources === null || !includes.call(current_sources, source))
	) {
		state_unsafe_mutation();
	}

	let new_value = should_proxy ? proxy(value) : value;

	if (DEV) {
		tag_proxy(new_value, /** @type {string} */ (source.label));
	}

	return internal_set(source, new_value, legacy_updates);
}

/**
 * @template V
 * @param {Source<V>} source
 * @param {V} value
 * @param {Effect[] | null} [updated_during_traversal]
 * @returns {V}
 */
function internal_set(source, value, updated_during_traversal = null) {
	if (!source.equals(value)) {
		old_values.set(source, is_destroying_effect ? value : source.v);

		var batch = Batch.ensure();
		batch.capture(source, value);

		if (DEV) {
			if (active_effect !== null) {
				source.updated ??= new Map();

				// For performance reasons, when not using $inspect.trace, we only start collecting stack traces
				// after the same source has been updated more than 5 times in the same flush cycle.
				const count = (source.updated.get('')?.count ?? 0) + 1;
				source.updated.set('', { error: /** @type {any} */ (null), count });

				if (count > 5) {
					const error = get_error('updated at');

					if (error !== null) {
						let entry = source.updated.get(error.stack);

						if (!entry) {
							entry = { error, count: 0 };
							source.updated.set(error.stack, entry);
						}

						entry.count++;
					}
				}
			}

			if (active_effect !== null) {
				source.set_during_effect = true;
			}
		}

		if ((source.f & DERIVED) !== 0) {
			const derived = /** @type {Derived} */ (source);

			// if we are assigning to a dirty derived we set it to clean/maybe dirty but we also eagerly execute it to track the dependencies
			if ((source.f & DIRTY) !== 0) {
				execute_derived(derived);
			}

			// During time traveling we don't want to reset the status so that
			// traversal of the graph in the other batches still happens
			if (batch_values === null) {
				update_derived_status(derived);
			}
		}

		source.wv = increment_write_version();

		// For debugging, in case you want to know which reactions are being scheduled:
		// log_reactions(source);
		mark_reactions(source, DIRTY, updated_during_traversal);

		// It's possible that the current reaction might not have up-to-date dependencies
		// whilst it's actively running. So in the case of ensuring it registers the reaction
		// properly for itself, we need to ensure the current effect actually gets
		// scheduled. i.e: `$effect(() => x++)`
		if (
			is_runes() &&
			active_effect !== null &&
			(active_effect.f & CLEAN) !== 0 &&
			(active_effect.f & (BRANCH_EFFECT | ROOT_EFFECT)) === 0
		) {
			if (untracked_writes === null) {
				set_untracked_writes([source]);
			} else {
				untracked_writes.push(source);
			}
		}

		if (!batch.is_fork && eager_effects.size > 0 && !eager_effects_deferred) {
			flush_eager_effects();
		}
	}

	return value;
}

function flush_eager_effects() {
	eager_effects_deferred = false;

	for (const effect of eager_effects) {
		// Mark clean inspect-effects as maybe dirty and then check their dirtiness
		// instead of just updating the effects - this way we avoid overfiring.
		if ((effect.f & CLEAN) !== 0) {
			set_signal_status(effect, MAYBE_DIRTY);
		}

		if (is_dirty(effect)) {
			update_effect(effect);
		}
	}

	eager_effects.clear();
}

/**
 * Silently (without using `get`) increment a source
 * @param {Source<number>} source
 */
function increment(source) {
	set(source, source.v + 1);
}

/**
 * @param {Value} signal
 * @param {number} status should be DIRTY or MAYBE_DIRTY
 * @param {Effect[] | null} updated_during_traversal
 * @returns {void}
 */
function mark_reactions(signal, status, updated_during_traversal) {
	var reactions = signal.reactions;
	if (reactions === null) return;

	var runes = is_runes();
	var length = reactions.length;

	for (var i = 0; i < length; i++) {
		var reaction = reactions[i];
		var flags = reaction.f;

		// In legacy mode, skip the current effect to prevent infinite loops
		if (!runes && reaction === active_effect) continue;

		// Inspect effects need to run immediately, so that the stack trace makes sense
		if (DEV && (flags & EAGER_EFFECT) !== 0) {
			eager_effects.add(reaction);
			continue;
		}

		var not_dirty = (flags & DIRTY) === 0;

		// don't set a DIRTY reaction to MAYBE_DIRTY
		if (not_dirty) {
			set_signal_status(reaction, status);
		}

		if ((flags & DERIVED) !== 0) {
			var derived = /** @type {Derived} */ (reaction);

			batch_values?.delete(derived);

			if ((flags & WAS_MARKED) === 0) {
				// Only connected deriveds can be reliably unmarked right away
				if (flags & CONNECTED) {
					reaction.f |= WAS_MARKED;
				}

				mark_reactions(derived, MAYBE_DIRTY, updated_during_traversal);
			}
		} else if (not_dirty) {
			var effect = /** @type {Effect} */ (reaction);

			if ((flags & BLOCK_EFFECT) !== 0 && eager_block_effects !== null) {
				eager_block_effects.add(effect);
			}

			if (updated_during_traversal !== null) {
				updated_during_traversal.push(effect);
			} else {
				schedule_effect(effect);
			}
		}
	}
}

/** @import { Source } from '#client' */

// TODO move all regexes into shared module?
const regex_is_valid_identifier = /^[a-zA-Z_$][a-zA-Z_$0-9]*$/;

/**
 * @template T
 * @param {T} value
 * @returns {T}
 */
function proxy(value) {
	// if non-proxyable, or is already a proxy, return `value`
	if (typeof value !== 'object' || value === null || STATE_SYMBOL in value) {
		return value;
	}

	const prototype = get_prototype_of(value);

	if (prototype !== object_prototype && prototype !== array_prototype) {
		return value;
	}

	/** @type {Map<any, Source<any>>} */
	var sources = new Map();
	var is_proxied_array = is_array(value);
	var version = state(0);

	var stack = DEV && tracing_mode_flag ? get_error('created at') : null;
	var parent_version = update_version;

	/**
	 * Executes the proxy in the context of the reaction it was originally created in, if any
	 * @template T
	 * @param {() => T} fn
	 */
	var with_parent = (fn) => {
		if (update_version === parent_version) {
			return fn();
		}

		// child source is being created after the initial proxy —
		// prevent it from being associated with the current reaction
		var reaction = active_reaction;
		var version = update_version;

		set_active_reaction(null);
		set_update_version(parent_version);

		var result = fn();

		set_active_reaction(reaction);
		set_update_version(version);

		return result;
	};

	if (is_proxied_array) {
		// We need to create the length source eagerly to ensure that
		// mutations to the array are properly synced with our proxy
		sources.set('length', state(/** @type {any[]} */ (value).length, stack));
		if (DEV) {
			value = /** @type {any} */ (inspectable_array(/** @type {any[]} */ (value)));
		}
	}

	/** Used in dev for $inspect.trace() */
	var path = '';
	let updating = false;
	/** @param {string} new_path */
	function update_path(new_path) {
		if (updating) return;
		updating = true;
		path = new_path;

		tag(version, `${path} version`);

		// rename all child sources and child proxies
		for (const [prop, source] of sources) {
			tag(source, get_label(path, prop));
		}
		updating = false;
	}

	return new Proxy(/** @type {any} */ (value), {
		defineProperty(_, prop, descriptor) {
			if (
				!('value' in descriptor) ||
				descriptor.configurable === false ||
				descriptor.enumerable === false ||
				descriptor.writable === false
			) {
				// we disallow non-basic descriptors, because unless they are applied to the
				// target object — which we avoid, so that state can be forked — we will run
				// afoul of the various invariants
				// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Proxy/Proxy/getOwnPropertyDescriptor#invariants
				state_descriptors_fixed();
			}
			var s = sources.get(prop);
			if (s === undefined) {
				with_parent(() => {
					var s = state(descriptor.value, stack);
					sources.set(prop, s);
					if (DEV && typeof prop === 'string') {
						tag(s, get_label(path, prop));
					}
					return s;
				});
			} else {
				set(s, descriptor.value, true);
			}

			return true;
		},

		deleteProperty(target, prop) {
			var s = sources.get(prop);

			if (s === undefined) {
				if (prop in target) {
					const s = with_parent(() => state(UNINITIALIZED, stack));
					sources.set(prop, s);
					increment(version);

					if (DEV) {
						tag(s, get_label(path, prop));
					}
				}
			} else {
				set(s, UNINITIALIZED);
				increment(version);
			}

			return true;
		},

		get(target, prop, receiver) {
			if (prop === STATE_SYMBOL) {
				return value;
			}

			if (DEV && prop === PROXY_PATH_SYMBOL) {
				return update_path;
			}

			var s = sources.get(prop);
			var exists = prop in target;

			// create a source, but only if it's an own property and not a prototype property
			if (s === undefined && (!exists || get_descriptor(target, prop)?.writable)) {
				s = with_parent(() => {
					var p = proxy(exists ? target[prop] : UNINITIALIZED);
					var s = state(p, stack);

					if (DEV) {
						tag(s, get_label(path, prop));
					}

					return s;
				});

				sources.set(prop, s);
			}

			if (s !== undefined) {
				var v = get(s);
				return v === UNINITIALIZED ? undefined : v;
			}

			return Reflect.get(target, prop, receiver);
		},

		getOwnPropertyDescriptor(target, prop) {
			var descriptor = Reflect.getOwnPropertyDescriptor(target, prop);

			if (descriptor && 'value' in descriptor) {
				var s = sources.get(prop);
				if (s) descriptor.value = get(s);
			} else if (descriptor === undefined) {
				var source = sources.get(prop);
				var value = source?.v;

				if (source !== undefined && value !== UNINITIALIZED) {
					return {
						enumerable: true,
						configurable: true,
						value,
						writable: true
					};
				}
			}

			return descriptor;
		},

		has(target, prop) {
			if (prop === STATE_SYMBOL) {
				return true;
			}

			var s = sources.get(prop);
			var has = (s !== undefined && s.v !== UNINITIALIZED) || Reflect.has(target, prop);

			if (
				s !== undefined ||
				(active_effect !== null && (!has || get_descriptor(target, prop)?.writable))
			) {
				if (s === undefined) {
					s = with_parent(() => {
						var p = has ? proxy(target[prop]) : UNINITIALIZED;
						var s = state(p, stack);

						if (DEV) {
							tag(s, get_label(path, prop));
						}

						return s;
					});

					sources.set(prop, s);
				}

				var value = get(s);
				if (value === UNINITIALIZED) {
					return false;
				}
			}

			return has;
		},

		set(target, prop, value, receiver) {
			var s = sources.get(prop);
			var has = prop in target;

			// variable.length = value -> clear all signals with index >= value
			if (is_proxied_array && prop === 'length') {
				for (var i = value; i < /** @type {Source<number>} */ (s).v; i += 1) {
					var other_s = sources.get(i + '');
					if (other_s !== undefined) {
						set(other_s, UNINITIALIZED);
					} else if (i in target) {
						// If the item exists in the original, we need to create an uninitialized source,
						// else a later read of the property would result in a source being created with
						// the value of the original item at that index.
						other_s = with_parent(() => state(UNINITIALIZED, stack));
						sources.set(i + '', other_s);

						if (DEV) {
							tag(other_s, get_label(path, i));
						}
					}
				}
			}

			// If we haven't yet created a source for this property, we need to ensure
			// we do so otherwise if we read it later, then the write won't be tracked and
			// the heuristics of effects will be different vs if we had read the proxied
			// object property before writing to that property.
			if (s === undefined) {
				if (!has || get_descriptor(target, prop)?.writable) {
					s = with_parent(() => state(undefined, stack));

					if (DEV) {
						tag(s, get_label(path, prop));
					}
					set(s, proxy(value));

					sources.set(prop, s);
				}
			} else {
				has = s.v !== UNINITIALIZED;

				var p = with_parent(() => proxy(value));
				set(s, p);
			}

			var descriptor = Reflect.getOwnPropertyDescriptor(target, prop);

			// Set the new value before updating any signals so that any listeners get the new value
			if (descriptor?.set) {
				descriptor.set.call(receiver, value);
			}

			if (!has) {
				// If we have mutated an array directly, we might need to
				// signal that length has also changed. Do it before updating metadata
				// to ensure that iterating over the array as a result of a metadata update
				// will not cause the length to be out of sync.
				if (is_proxied_array && typeof prop === 'string') {
					var ls = /** @type {Source<number>} */ (sources.get('length'));
					var n = Number(prop);

					if (Number.isInteger(n) && n >= ls.v) {
						set(ls, n + 1);
					}
				}

				increment(version);
			}

			return true;
		},

		ownKeys(target) {
			get(version);

			var own_keys = Reflect.ownKeys(target).filter((key) => {
				var source = sources.get(key);
				return source === undefined || source.v !== UNINITIALIZED;
			});

			for (var [key, source] of sources) {
				if (source.v !== UNINITIALIZED && !(key in target)) {
					own_keys.push(key);
				}
			}

			return own_keys;
		},

		setPrototypeOf() {
			state_prototype_fixed();
		}
	});
}

/**
 * @param {string} path
 * @param {string | symbol} prop
 */
function get_label(path, prop) {
	if (typeof prop === 'symbol') return `${path}[Symbol(${prop.description ?? ''})]`;
	if (regex_is_valid_identifier.test(prop)) return `${path}.${prop}`;
	return /^\d+$/.test(prop) ? `${path}[${prop}]` : `${path}['${prop}']`;
}

/**
 * @param {any} value
 */
function get_proxied_value(value) {
	try {
		if (value !== null && typeof value === 'object' && STATE_SYMBOL in value) {
			return value[STATE_SYMBOL];
		}
	} catch {
		// the above if check can throw an error if the value in question
		// is the contentWindow of an iframe on another domain, in which
		// case we want to just return the value (because it's definitely
		// not a proxied value) so we don't break any JavaScript interacting
		// with that iframe (such as various payment companies client side
		// JavaScript libraries interacting with their iframes on the same
		// domain)
	}

	return value;
}

/**
 * @param {any} a
 * @param {any} b
 */
function is(a, b) {
	return Object.is(get_proxied_value(a), get_proxied_value(b));
}

const ARRAY_MUTATING_METHODS = new Set([
	'copyWithin',
	'fill',
	'pop',
	'push',
	'reverse',
	'shift',
	'sort',
	'splice',
	'unshift'
]);

/**
 * Wrap array mutating methods so $inspect is triggered only once and
 * to prevent logging an array in intermediate state (e.g. with an empty slot)
 * @param {any[]} array
 */
function inspectable_array(array) {
	return new Proxy(array, {
		get(target, prop, receiver) {
			var value = Reflect.get(target, prop, receiver);
			if (!ARRAY_MUTATING_METHODS.has(/** @type {string} */ (prop))) {
				return value;
			}

			/**
			 * @this {any[]}
			 * @param {any[]} args
			 */
			return function (...args) {
				set_eager_effects_deferred();
				var result = value.apply(this, args);
				flush_eager_effects();
				return result;
			};
		}
	});
}

function init_array_prototype_warnings() {
	const array_prototype = Array.prototype;
	// The REPL ends up here over and over, and this prevents it from adding more and more patches
	// of the same kind to the prototype, which would slow down everything over time.
	// @ts-expect-error
	const cleanup = Array.__svelte_cleanup;
	if (cleanup) {
		cleanup();
	}

	const { indexOf, lastIndexOf, includes } = array_prototype;

	array_prototype.indexOf = function (item, from_index) {
		const index = indexOf.call(this, item, from_index);

		if (index === -1) {
			for (let i = from_index ?? 0; i < this.length; i += 1) {
				if (get_proxied_value(this[i]) === item) {
					state_proxy_equality_mismatch('array.indexOf(...)');
					break;
				}
			}
		}

		return index;
	};

	array_prototype.lastIndexOf = function (item, from_index) {
		// we need to specify this.length - 1 because it's probably using something like
		// `arguments` inside so passing undefined is different from not passing anything
		const index = lastIndexOf.call(this, item, from_index ?? this.length - 1);

		if (index === -1) {
			for (let i = 0; i <= (from_index ?? this.length - 1); i += 1) {
				if (get_proxied_value(this[i]) === item) {
					state_proxy_equality_mismatch('array.lastIndexOf(...)');
					break;
				}
			}
		}

		return index;
	};

	array_prototype.includes = function (item, from_index) {
		const has = includes.call(this, item, from_index);

		if (!has) {
			for (let i = 0; i < this.length; i += 1) {
				if (get_proxied_value(this[i]) === item) {
					state_proxy_equality_mismatch('array.includes(...)');
					break;
				}
			}
		}

		return has;
	};

	// @ts-expect-error
	Array.__svelte_cleanup = () => {
		array_prototype.indexOf = indexOf;
		array_prototype.lastIndexOf = lastIndexOf;
		array_prototype.includes = includes;
	};
}

/**
 * @param {any} a
 * @param {any} b
 * @param {boolean} equal
 * @returns {boolean}
 */
function strict_equals(a, b, equal = true) {
	// try-catch needed because this tries to read properties of `a` and `b`,
	// which could be disallowed for example in a secure context
	try {
		if ((a === b) !== (get_proxied_value(a) === get_proxied_value(b))) {
			state_proxy_equality_mismatch(equal ? '===' : '!==');
		}
	} catch {}

	return (a === b) === equal;
}

/**
 * @param {any} a
 * @param {any} b
 * @param {boolean} equal
 * @returns {boolean}
 */
function equals(a, b, equal = true) {
	if ((a == b) !== (get_proxied_value(a) == get_proxied_value(b))) {
		state_proxy_equality_mismatch(equal ? '==' : '!=');
	}

	return (a == b) === equal;
}

/** @import { Effect, TemplateNode } from '#client' */

// export these for reference in the compiled code, making global name deduplication unnecessary
/** @type {Window} */
var $window;

/** @type {boolean} */
var is_firefox;

/** @type {() => Node | null} */
var first_child_getter;
/** @type {() => Node | null} */
var next_sibling_getter;

/**
 * Initialize these lazily to avoid issues when using the runtime in a server context
 * where these globals are not available while avoiding a separate server entry point
 */
function init_operations() {
	if ($window !== undefined) {
		return;
	}

	$window = window;
	is_firefox = /Firefox/.test(navigator.userAgent);

	var element_prototype = Element.prototype;
	var node_prototype = Node.prototype;
	var text_prototype = Text.prototype;

	// @ts-ignore
	first_child_getter = get_descriptor(node_prototype, 'firstChild').get;
	// @ts-ignore
	next_sibling_getter = get_descriptor(node_prototype, 'nextSibling').get;

	if (is_extensible(element_prototype)) {
		// the following assignments improve perf of lookups on DOM nodes
		// @ts-expect-error
		element_prototype.__click = undefined;
		// @ts-expect-error
		element_prototype.__className = undefined;
		// @ts-expect-error
		element_prototype.__attributes = null;
		// @ts-expect-error
		element_prototype.__style = undefined;
		// @ts-expect-error
		element_prototype.__e = undefined;
	}

	if (is_extensible(text_prototype)) {
		// @ts-expect-error
		text_prototype.__t = undefined;
	}

	if (DEV) {
		// @ts-expect-error
		element_prototype.__svelte_meta = null;

		init_array_prototype_warnings();
	}
}

/**
 * @param {string} value
 * @returns {Text}
 */
function create_text(value = '') {
	return document.createTextNode(value);
}

/**
 * @template {Node} N
 * @param {N} node
 */
/*@__NO_SIDE_EFFECTS__*/
function get_first_child(node) {
	return /** @type {TemplateNode | null} */ (first_child_getter.call(node));
}

/**
 * @template {Node} N
 * @param {N} node
 */
/*@__NO_SIDE_EFFECTS__*/
function get_next_sibling(node) {
	return /** @type {TemplateNode | null} */ (next_sibling_getter.call(node));
}

/**
 * Don't mark this as side-effect-free, hydration needs to walk all nodes
 * @template {Node} N
 * @param {N} node
 * @param {boolean} is_text
 * @returns {TemplateNode | null}
 */
function child(node, is_text) {
	{
		return get_first_child(node);
	}
}

/**
 * Don't mark this as side-effect-free, hydration needs to walk all nodes
 * @param {TemplateNode} node
 * @param {boolean} [is_text]
 * @returns {TemplateNode | null}
 */
function first_child(node, is_text = false) {
	{
		var first = get_first_child(node);

		// TODO prevent user comments with the empty string when preserveComments is true
		if (first instanceof Comment && first.data === '') return get_next_sibling(first);

		return first;
	}
}

/**
 * Don't mark this as side-effect-free, hydration needs to walk all nodes
 * @param {TemplateNode} node
 * @param {number} count
 * @param {boolean} is_text
 * @returns {TemplateNode | null}
 */
function sibling(node, count = 1, is_text = false) {
	let next_sibling = node;

	while (count--) {
		next_sibling = /** @type {TemplateNode} */ (get_next_sibling(next_sibling));
	}

	{
		return next_sibling;
	}
}

/**
 * @template {Node} N
 * @param {N} node
 * @returns {void}
 */
function clear_text_content(node) {
	node.textContent = '';
}

/**
 * Returns `true` if we're updating the current block, for example `condition` in
 * an `{#if condition}` block just changed. In this case, the branch should be
 * appended (or removed) at the same time as other updates within the
 * current `<svelte:boundary>`
 */
function should_defer_append() {
	return false;
}

/**
 * @template {keyof HTMLElementTagNameMap | string} T
 * @param {T} tag
 * @param {string} [namespace]
 * @param {string} [is]
 * @returns {T extends keyof HTMLElementTagNameMap ? HTMLElementTagNameMap[T] : Element}
 */
function create_element(tag, namespace, is) {
	let options = is ? { is } : undefined;
	return /** @type {T extends keyof HTMLElementTagNameMap ? HTMLElementTagNameMap[T] : Element} */ (
		document.createElementNS(namespace ?? NAMESPACE_HTML, tag, options)
	);
}

let listening_to_form_reset = false;

function add_form_reset_listener() {
	if (!listening_to_form_reset) {
		listening_to_form_reset = true;
		document.addEventListener(
			'reset',
			(evt) => {
				// Needs to happen one tick later or else the dom properties of the form
				// elements have not updated to their reset values yet
				Promise.resolve().then(() => {
					if (!evt.defaultPrevented) {
						for (const e of /**@type {HTMLFormElement} */ (evt.target).elements) {
							// @ts-expect-error
							e.__on_r?.();
						}
					}
				});
			},
			// In the capture phase to guarantee we get noticed of it (no possibility of stopPropagation)
			{ capture: true }
		);
	}
}

/**
 * @template T
 * @param {() => T} fn
 */
function without_reactive_context(fn) {
	var previous_reaction = active_reaction;
	var previous_effect = active_effect;
	set_active_reaction(null);
	set_active_effect(null);
	try {
		return fn();
	} finally {
		set_active_reaction(previous_reaction);
		set_active_effect(previous_effect);
	}
}

/**
 * Listen to the given event, and then instantiate a global form reset listener if not already done,
 * to notify all bindings when the form is reset
 * @param {HTMLElement} element
 * @param {string} event
 * @param {(is_reset?: true) => void} handler
 * @param {(is_reset?: true) => void} [on_reset]
 */
function listen_to_event_and_reset_event(element, event, handler, on_reset = handler) {
	element.addEventListener(event, () => without_reactive_context(handler));
	// @ts-expect-error
	const prev = element.__on_r;
	if (prev) {
		// special case for checkbox that can have multiple binds (group & checked)
		// @ts-expect-error
		element.__on_r = () => {
			prev();
			on_reset(true);
		};
	} else {
		// @ts-expect-error
		element.__on_r = () => on_reset(true);
	}

	add_form_reset_listener();
}

/** @import { Blocker, ComponentContext, ComponentContextLegacy, Derived, Effect, TemplateNode, TransitionManager } from '#client' */

/**
 * @param {'$effect' | '$effect.pre' | '$inspect'} rune
 */
function validate_effect(rune) {
	if (active_effect === null) {
		if (active_reaction === null) {
			effect_orphan(rune);
		}

		effect_in_unowned_derived();
	}

	if (is_destroying_effect) {
		effect_in_teardown(rune);
	}
}

/**
 * @param {Effect} effect
 * @param {Effect} parent_effect
 */
function push_effect(effect, parent_effect) {
	var parent_last = parent_effect.last;
	if (parent_last === null) {
		parent_effect.last = parent_effect.first = effect;
	} else {
		parent_last.next = effect;
		effect.prev = parent_last;
		parent_effect.last = effect;
	}
}

/**
 * @param {number} type
 * @param {null | (() => void | (() => void))} fn
 * @returns {Effect}
 */
function create_effect(type, fn) {
	var parent = active_effect;

	if (DEV) {
		// Ensure the parent is never an inspect effect
		while (parent !== null && (parent.f & EAGER_EFFECT) !== 0) {
			parent = parent.parent;
		}
	}

	if (parent !== null && (parent.f & INERT) !== 0) {
		type |= INERT;
	}

	/** @type {Effect} */
	var effect = {
		ctx: component_context,
		deps: null,
		nodes: null,
		f: type | DIRTY | CONNECTED,
		first: null,
		fn,
		last: null,
		next: null,
		parent,
		b: parent && parent.b,
		prev: null,
		teardown: null,
		wv: 0,
		ac: null
	};

	if (DEV) {
		effect.component_function = dev_current_component_function;
	}

	current_batch?.register_created_effect(effect);

	/** @type {Effect | null} */
	var e = effect;

	if ((type & EFFECT) !== 0) {
		if (collected_effects !== null) {
			// created during traversal — collect and run afterwards
			collected_effects.push(effect);
		} else {
			// schedule for later
			Batch.ensure().schedule(effect);
		}
	} else if (fn !== null) {
		try {
			update_effect(effect);
		} catch (e) {
			destroy_effect(effect);
			throw e;
		}

		// if an effect doesn't need to be kept in the tree (because it
		// won't re-run, has no DOM, and has no teardown etc)
		// then we skip it and go to its child (if any)
		if (
			e.deps === null &&
			e.teardown === null &&
			e.nodes === null &&
			e.first === e.last && // either `null`, or a singular child
			(e.f & EFFECT_PRESERVED) === 0
		) {
			e = e.first;
			if ((type & BLOCK_EFFECT) !== 0 && (type & EFFECT_TRANSPARENT) !== 0 && e !== null) {
				e.f |= EFFECT_TRANSPARENT;
			}
		}
	}

	if (e !== null) {
		e.parent = parent;

		if (parent !== null) {
			push_effect(e, parent);
		}

		// if we're in a derived, add the effect there too
		if (
			active_reaction !== null &&
			(active_reaction.f & DERIVED) !== 0 &&
			(type & ROOT_EFFECT) === 0
		) {
			var derived = /** @type {Derived} */ (active_reaction);
			(derived.effects ??= []).push(e);
		}
	}

	return effect;
}

/**
 * Internal representation of `$effect.tracking()`
 * @returns {boolean}
 */
function effect_tracking() {
	return active_reaction !== null && !untracking;
}

/**
 * @param {() => void} fn
 */
function teardown(fn) {
	const effect = create_effect(RENDER_EFFECT, null);
	set_signal_status(effect, CLEAN);
	effect.teardown = fn;
	return effect;
}

/**
 * Internal representation of `$effect(...)`
 * @param {() => void | (() => void)} fn
 */
function user_effect(fn) {
	validate_effect('$effect');

	if (DEV) {
		define_property(fn, 'name', {
			value: '$effect'
		});
	}

	// Non-nested `$effect(...)` in a component should be deferred
	// until the component is mounted
	var flags = /** @type {Effect} */ (active_effect).f;
	var defer = !active_reaction && (flags & BRANCH_EFFECT) !== 0 && (flags & REACTION_RAN) === 0;

	if (defer) {
		// Top-level `$effect(...)` in an unmounted component — defer until mount
		var context = /** @type {ComponentContext} */ (component_context);
		(context.e ??= []).push(fn);
	} else {
		// Everything else — create immediately
		return create_user_effect(fn);
	}
}

/**
 * @param {() => void | (() => void)} fn
 */
function create_user_effect(fn) {
	return create_effect(EFFECT | USER_EFFECT, fn);
}

/**
 * Internal representation of `$effect.pre(...)`
 * @param {() => void | (() => void)} fn
 * @returns {Effect}
 */
function user_pre_effect(fn) {
	validate_effect('$effect.pre');
	if (DEV) {
		define_property(fn, 'name', {
			value: '$effect.pre'
		});
	}
	return create_effect(RENDER_EFFECT | USER_EFFECT, fn);
}

/**
 * An effect root whose children can transition out
 * @param {() => void} fn
 * @returns {(options?: { outro?: boolean }) => Promise<void>}
 */
function component_root(fn) {
	Batch.ensure();
	const effect = create_effect(ROOT_EFFECT | EFFECT_PRESERVED, fn);

	return (options = {}) => {
		return new Promise((fulfil) => {
			if (options.outro) {
				pause_effect(effect, () => {
					destroy_effect(effect);
					fulfil(undefined);
				});
			} else {
				destroy_effect(effect);
				fulfil(undefined);
			}
		});
	};
}

/**
 * @param {() => void | (() => void)} fn
 * @returns {Effect}
 */
function effect(fn) {
	return create_effect(EFFECT, fn);
}

/**
 * Internal representation of `$: ..`
 * @param {() => any} deps
 * @param {() => void | (() => void)} fn
 */
function legacy_pre_effect(deps, fn) {
	var context = /** @type {ComponentContextLegacy} */ (component_context);

	/** @type {{ effect: null | Effect, ran: boolean, deps: () => any }} */
	var token = { effect: null, ran: false, deps };

	context.l.$.push(token);

	token.effect = render_effect(() => {
		deps();

		// If this legacy pre effect has already run before the end of the reset, then
		// bail out to emulate the same behavior.
		if (token.ran) return;

		token.ran = true;

		var effect = /** @type {Effect} */ (active_effect);

		// here, we lie: by setting `active_effect` to be the parent branch, any writes
		// that happen inside `fn` will _not_ cause an unnecessary reschedule, because
		// the affected effects will be children of `active_effect`. this is safe
		// because these effects are known to run in the correct order
		try {
			set_active_effect(effect.parent);
			untrack(fn);
		} finally {
			set_active_effect(effect);
		}
	});
}

function legacy_pre_effect_reset() {
	var context = /** @type {ComponentContextLegacy} */ (component_context);

	render_effect(() => {
		// Run dirty `$:` statements
		for (var token of context.l.$) {
			token.deps();

			var effect = token.effect;

			// If the effect is CLEAN, then make it MAYBE_DIRTY. This ensures we traverse through
			// the effects dependencies and correctly ensure each dependency is up-to-date.
			if ((effect.f & CLEAN) !== 0 && effect.deps !== null) {
				set_signal_status(effect, MAYBE_DIRTY);
			}

			if (is_dirty(effect)) {
				update_effect(effect);
			}

			token.ran = false;
		}
	});
}

/**
 * @param {() => void | (() => void)} fn
 * @returns {Effect}
 */
function async_effect(fn) {
	return create_effect(ASYNC | EFFECT_PRESERVED, fn);
}

/**
 * @param {() => void | (() => void)} fn
 * @returns {Effect}
 */
function render_effect(fn, flags = 0) {
	return create_effect(RENDER_EFFECT | flags, fn);
}

/**
 * @param {(...expressions: any) => void | (() => void)} fn
 * @param {Array<() => any>} sync
 * @param {Array<() => Promise<any>>} async
 * @param {Blocker[]} blockers
 */
function template_effect(fn, sync = [], async = [], blockers = []) {
	flatten(blockers, sync, async, (values) => {
		create_effect(RENDER_EFFECT, () => fn(...values.map(get)));
	});
}

/**
 * @param {(() => void)} fn
 * @param {number} flags
 */
function block(fn, flags = 0) {
	var effect = create_effect(BLOCK_EFFECT | flags, fn);
	if (DEV) {
		effect.dev_stack = dev_stack;
	}
	return effect;
}

/**
 * @param {(() => void)} fn
 */
function branch(fn) {
	return create_effect(BRANCH_EFFECT | EFFECT_PRESERVED, fn);
}

/**
 * @param {Effect} effect
 */
function execute_effect_teardown(effect) {
	var teardown = effect.teardown;
	if (teardown !== null) {
		const previously_destroying_effect = is_destroying_effect;
		const previous_reaction = active_reaction;
		set_is_destroying_effect(true);
		set_active_reaction(null);
		try {
			teardown.call(null);
		} finally {
			set_is_destroying_effect(previously_destroying_effect);
			set_active_reaction(previous_reaction);
		}
	}
}

/**
 * @param {Effect} signal
 * @param {boolean} remove_dom
 * @returns {void}
 */
function destroy_effect_children(signal, remove_dom = false) {
	var effect = signal.first;
	signal.first = signal.last = null;

	while (effect !== null) {
		const controller = effect.ac;

		if (controller !== null) {
			without_reactive_context(() => {
				controller.abort(STALE_REACTION);
			});
		}

		var next = effect.next;

		if ((effect.f & ROOT_EFFECT) !== 0) {
			// this is now an independent root
			effect.parent = null;
		} else {
			destroy_effect(effect, remove_dom);
		}

		effect = next;
	}
}

/**
 * @param {Effect} signal
 * @returns {void}
 */
function destroy_block_effect_children(signal) {
	var effect = signal.first;

	while (effect !== null) {
		var next = effect.next;
		if ((effect.f & BRANCH_EFFECT) === 0) {
			destroy_effect(effect);
		}
		effect = next;
	}
}

/**
 * @param {Effect} effect
 * @param {boolean} [remove_dom]
 * @returns {void}
 */
function destroy_effect(effect, remove_dom = true) {
	var removed = false;

	if (
		(remove_dom || (effect.f & HEAD_EFFECT) !== 0) &&
		effect.nodes !== null &&
		effect.nodes.end !== null
	) {
		remove_effect_dom(effect.nodes.start, /** @type {TemplateNode} */ (effect.nodes.end));
		removed = true;
	}

	set_signal_status(effect, DESTROYING);
	destroy_effect_children(effect, remove_dom && !removed);
	remove_reactions(effect, 0);

	var transitions = effect.nodes && effect.nodes.t;

	if (transitions !== null) {
		for (const transition of transitions) {
			transition.stop();
		}
	}

	execute_effect_teardown(effect);

	effect.f ^= DESTROYING;
	effect.f |= DESTROYED;

	var parent = effect.parent;

	// If the parent doesn't have any children, then skip this work altogether
	if (parent !== null && parent.first !== null) {
		unlink_effect(effect);
	}

	if (DEV) {
		effect.component_function = null;
	}

	// `first` and `child` are nulled out in destroy_effect_children
	// we don't null out `parent` so that error propagation can work correctly
	effect.next =
		effect.prev =
		effect.teardown =
		effect.ctx =
		effect.deps =
		effect.fn =
		effect.nodes =
		effect.ac =
		effect.b =
			null;
}

/**
 *
 * @param {TemplateNode | null} node
 * @param {TemplateNode} end
 */
function remove_effect_dom(node, end) {
	while (node !== null) {
		/** @type {TemplateNode | null} */
		var next = node === end ? null : get_next_sibling(node);

		node.remove();
		node = next;
	}
}

/**
 * Detach an effect from the effect tree, freeing up memory and
 * reducing the amount of work that happens on subsequent traversals
 * @param {Effect} effect
 */
function unlink_effect(effect) {
	var parent = effect.parent;
	var prev = effect.prev;
	var next = effect.next;

	if (prev !== null) prev.next = next;
	if (next !== null) next.prev = prev;

	if (parent !== null) {
		if (parent.first === effect) parent.first = next;
		if (parent.last === effect) parent.last = prev;
	}
}

/**
 * When a block effect is removed, we don't immediately destroy it or yank it
 * out of the DOM, because it might have transitions. Instead, we 'pause' it.
 * It stays around (in memory, and in the DOM) until outro transitions have
 * completed, and if the state change is reversed then we _resume_ it.
 * A paused effect does not update, and the DOM subtree becomes inert.
 * @param {Effect} effect
 * @param {() => void} [callback]
 * @param {boolean} [destroy]
 */
function pause_effect(effect, callback, destroy = true) {
	/** @type {TransitionManager[]} */
	var transitions = [];

	pause_children(effect, transitions, true);

	var fn = () => {
		if (destroy) destroy_effect(effect);
		if (callback) callback();
	};

	var remaining = transitions.length;
	if (remaining > 0) {
		var check = () => --remaining || fn();
		for (var transition of transitions) {
			transition.out(check);
		}
	} else {
		fn();
	}
}

/**
 * @param {Effect} effect
 * @param {TransitionManager[]} transitions
 * @param {boolean} local
 */
function pause_children(effect, transitions, local) {
	if ((effect.f & INERT) !== 0) return;
	effect.f ^= INERT;

	var t = effect.nodes && effect.nodes.t;

	if (t !== null) {
		for (const transition of t) {
			if (transition.is_global || local) {
				transitions.push(transition);
			}
		}
	}

	var child = effect.first;

	while (child !== null) {
		var sibling = child.next;
		var transparent =
			(child.f & EFFECT_TRANSPARENT) !== 0 ||
			// If this is a branch effect without a block effect parent,
			// it means the parent block effect was pruned. In that case,
			// transparency information was transferred to the branch effect.
			((child.f & BRANCH_EFFECT) !== 0 && (effect.f & BLOCK_EFFECT) !== 0);
		// TODO we don't need to call pause_children recursively with a linked list in place
		// it's slightly more involved though as we have to account for `transparent` changing
		// through the tree.
		pause_children(child, transitions, transparent ? local : false);
		child = sibling;
	}
}

/**
 * The opposite of `pause_effect`. We call this if (for example)
 * `x` becomes falsy then truthy: `{#if x}...{/if}`
 * @param {Effect} effect
 */
function resume_effect(effect) {
	resume_children(effect, true);
}

/**
 * @param {Effect} effect
 * @param {boolean} local
 */
function resume_children(effect, local) {
	if ((effect.f & INERT) === 0) return;
	effect.f ^= INERT;

	// If a dependency of this effect changed while it was paused,
	// schedule the effect to update. we don't use `is_dirty`
	// here because we don't want to eagerly recompute a derived like
	// `{#if foo}{foo.bar()}{/if}` if `foo` is now `undefined
	if ((effect.f & CLEAN) === 0) {
		set_signal_status(effect, DIRTY);
		Batch.ensure().schedule(effect); // Assumption: This happens during the commit phase of the batch, causing another flush, but it's safe
	}

	var child = effect.first;

	while (child !== null) {
		var sibling = child.next;
		var transparent = (child.f & EFFECT_TRANSPARENT) !== 0 || (child.f & BRANCH_EFFECT) !== 0;
		// TODO we don't need to call resume_children recursively with a linked list in place
		// it's slightly more involved though as we have to account for `transparent` changing
		// through the tree.
		resume_children(child, transparent ? local : false);
		child = sibling;
	}

	var t = effect.nodes && effect.nodes.t;

	if (t !== null) {
		for (const transition of t) {
			if (transition.is_global || local) {
				transition.in();
			}
		}
	}
}

/**
 * @param {Effect} effect
 * @param {DocumentFragment} fragment
 */
function move_effect(effect, fragment) {
	if (!effect.nodes) return;

	/** @type {TemplateNode | null} */
	var node = effect.nodes.start;
	var end = effect.nodes.end;

	while (node !== null) {
		/** @type {TemplateNode | null} */
		var next = node === end ? null : get_next_sibling(node);

		fragment.append(node);
		node = next;
	}
}

/** @import { Derived, Effect, Reaction, Source, Value } from '#client' */

let is_updating_effect = false;

let is_destroying_effect = false;

/** @param {boolean} value */
function set_is_destroying_effect(value) {
	is_destroying_effect = value;
}

/** @type {null | Reaction} */
let active_reaction = null;

let untracking = false;

/** @param {null | Reaction} reaction */
function set_active_reaction(reaction) {
	active_reaction = reaction;
}

/** @type {null | Effect} */
let active_effect = null;

/** @param {null | Effect} effect */
function set_active_effect(effect) {
	active_effect = effect;
}

/**
 * When sources are created within a reaction, reading and writing
 * them within that reaction should not cause a re-run
 * @type {null | Source[]}
 */
let current_sources = null;

/** @param {Value} value */
function push_reaction_value(value) {
	if (active_reaction !== null && (!async_mode_flag )) {
		if (current_sources === null) {
			current_sources = [value];
		} else {
			current_sources.push(value);
		}
	}
}

/**
 * The dependencies of the reaction that is currently being executed. In many cases,
 * the dependencies are unchanged between runs, and so this will be `null` unless
 * and until a new dependency is accessed — we track this via `skipped_deps`
 * @type {null | Value[]}
 */
let new_deps = null;

let skipped_deps = 0;

/**
 * Tracks writes that the effect it's executed in doesn't listen to yet,
 * so that the dependency can be added to the effect later on if it then reads it
 * @type {null | Source[]}
 */
let untracked_writes = null;

/** @param {null | Source[]} value */
function set_untracked_writes(value) {
	untracked_writes = value;
}

/**
 * @type {number} Used by sources and deriveds for handling updates.
 * Version starts from 1 so that unowned deriveds differentiate between a created effect and a run one for tracing
 **/
let write_version = 1;

/** @type {number} Used to version each read of a source of derived to avoid duplicating depedencies inside a reaction */
let read_version = 0;

let update_version = read_version;

/** @param {number} value */
function set_update_version(value) {
	update_version = value;
}

function increment_write_version() {
	return ++write_version;
}

/**
 * Determines whether a derived or effect is dirty.
 * If it is MAYBE_DIRTY, will set the status to CLEAN
 * @param {Reaction} reaction
 * @returns {boolean}
 */
function is_dirty(reaction) {
	var flags = reaction.f;

	if ((flags & DIRTY) !== 0) {
		return true;
	}

	if (flags & DERIVED) {
		reaction.f &= ~WAS_MARKED;
	}

	if ((flags & MAYBE_DIRTY) !== 0) {
		var dependencies = /** @type {Value[]} */ (reaction.deps);
		var length = dependencies.length;

		for (var i = 0; i < length; i++) {
			var dependency = dependencies[i];

			if (is_dirty(/** @type {Derived} */ (dependency))) {
				update_derived(/** @type {Derived} */ (dependency));
			}

			if (dependency.wv > reaction.wv) {
				return true;
			}
		}

		if (
			(flags & CONNECTED) !== 0 &&
			// During time traveling we don't want to reset the status so that
			// traversal of the graph in the other batches still happens
			batch_values === null
		) {
			set_signal_status(reaction, CLEAN);
		}
	}

	return false;
}

/**
 * @param {Value} signal
 * @param {Effect} effect
 * @param {boolean} [root]
 */
function schedule_possible_effect_self_invalidation(signal, effect, root = true) {
	var reactions = signal.reactions;
	if (reactions === null) return;

	if (current_sources !== null && includes.call(current_sources, signal)) {
		return;
	}

	for (var i = 0; i < reactions.length; i++) {
		var reaction = reactions[i];

		if ((reaction.f & DERIVED) !== 0) {
			schedule_possible_effect_self_invalidation(/** @type {Derived} */ (reaction), effect, false);
		} else if (effect === reaction) {
			if (root) {
				set_signal_status(reaction, DIRTY);
			} else if ((reaction.f & CLEAN) !== 0) {
				set_signal_status(reaction, MAYBE_DIRTY);
			}
			schedule_effect(/** @type {Effect} */ (reaction));
		}
	}
}

/** @param {Reaction} reaction */
function update_reaction(reaction) {
	var previous_deps = new_deps;
	var previous_skipped_deps = skipped_deps;
	var previous_untracked_writes = untracked_writes;
	var previous_reaction = active_reaction;
	var previous_sources = current_sources;
	var previous_component_context = component_context;
	var previous_untracking = untracking;
	var previous_update_version = update_version;

	var flags = reaction.f;

	new_deps = /** @type {null | Value[]} */ (null);
	skipped_deps = 0;
	untracked_writes = null;
	active_reaction = (flags & (BRANCH_EFFECT | ROOT_EFFECT)) === 0 ? reaction : null;

	current_sources = null;
	set_component_context(reaction.ctx);
	untracking = false;
	update_version = ++read_version;

	if (reaction.ac !== null) {
		without_reactive_context(() => {
			/** @type {AbortController} */ (reaction.ac).abort(STALE_REACTION);
		});

		reaction.ac = null;
	}

	try {
		reaction.f |= REACTION_IS_UPDATING;
		var fn = /** @type {Function} */ (reaction.fn);
		var result = fn();
		reaction.f |= REACTION_RAN;
		var deps = reaction.deps;

		// Don't remove reactions during fork;
		// they must remain for when fork is discarded
		var is_fork = current_batch?.is_fork;

		if (new_deps !== null) {
			var i;

			if (!is_fork) {
				remove_reactions(reaction, skipped_deps);
			}

			if (deps !== null && skipped_deps > 0) {
				deps.length = skipped_deps + new_deps.length;
				for (i = 0; i < new_deps.length; i++) {
					deps[skipped_deps + i] = new_deps[i];
				}
			} else {
				reaction.deps = deps = new_deps;
			}

			if (effect_tracking() && (reaction.f & CONNECTED) !== 0) {
				for (i = skipped_deps; i < deps.length; i++) {
					(deps[i].reactions ??= []).push(reaction);
				}
			}
		} else if (!is_fork && deps !== null && skipped_deps < deps.length) {
			remove_reactions(reaction, skipped_deps);
			deps.length = skipped_deps;
		}

		// If we're inside an effect and we have untracked writes, then we need to
		// ensure that if any of those untracked writes result in re-invalidation
		// of the current effect, then that happens accordingly
		if (
			is_runes() &&
			untracked_writes !== null &&
			!untracking &&
			deps !== null &&
			(reaction.f & (DERIVED | MAYBE_DIRTY | DIRTY)) === 0
		) {
			for (i = 0; i < /** @type {Source[]} */ (untracked_writes).length; i++) {
				schedule_possible_effect_self_invalidation(
					untracked_writes[i],
					/** @type {Effect} */ (reaction)
				);
			}
		}

		// If we are returning to an previous reaction then
		// we need to increment the read version to ensure that
		// any dependencies in this reaction aren't marked with
		// the same version
		if (previous_reaction !== null && previous_reaction !== reaction) {
			read_version++;

			// update the `rv` of the previous reaction's deps — both existing and new —
			// so that they are not added again
			if (previous_reaction.deps !== null) {
				for (let i = 0; i < previous_skipped_deps; i += 1) {
					previous_reaction.deps[i].rv = read_version;
				}
			}

			if (previous_deps !== null) {
				for (const dep of previous_deps) {
					dep.rv = read_version;
				}
			}

			if (untracked_writes !== null) {
				if (previous_untracked_writes === null) {
					previous_untracked_writes = untracked_writes;
				} else {
					previous_untracked_writes.push(.../** @type {Source[]} */ (untracked_writes));
				}
			}
		}

		if ((reaction.f & ERROR_VALUE) !== 0) {
			reaction.f ^= ERROR_VALUE;
		}

		return result;
	} catch (error) {
		return handle_error(error);
	} finally {
		reaction.f ^= REACTION_IS_UPDATING;
		new_deps = previous_deps;
		skipped_deps = previous_skipped_deps;
		untracked_writes = previous_untracked_writes;
		active_reaction = previous_reaction;
		current_sources = previous_sources;
		set_component_context(previous_component_context);
		untracking = previous_untracking;
		update_version = previous_update_version;
	}
}

/**
 * @template V
 * @param {Reaction} signal
 * @param {Value<V>} dependency
 * @returns {void}
 */
function remove_reaction(signal, dependency) {
	let reactions = dependency.reactions;
	if (reactions !== null) {
		var index = index_of.call(reactions, signal);
		if (index !== -1) {
			var new_length = reactions.length - 1;
			if (new_length === 0) {
				reactions = dependency.reactions = null;
			} else {
				// Swap with last element and then remove.
				reactions[index] = reactions[new_length];
				reactions.pop();
			}
		}
	}

	// If the derived has no reactions, then we can disconnect it from the graph,
	// allowing it to either reconnect in the future, or be GC'd by the VM.
	if (
		reactions === null &&
		(dependency.f & DERIVED) !== 0 &&
		// Destroying a child effect while updating a parent effect can cause a dependency to appear
		// to be unused, when in fact it is used by the currently-updating parent. Checking `new_deps`
		// allows us to skip the expensive work of disconnecting and immediately reconnecting it
		(new_deps === null || !includes.call(new_deps, dependency))
	) {
		var derived = /** @type {Derived} */ (dependency);

		// If we are working with a derived that is owned by an effect, then mark it as being
		// disconnected and remove the mark flag, as it cannot be reliably removed otherwise
		if ((derived.f & CONNECTED) !== 0) {
			derived.f ^= CONNECTED;
			derived.f &= ~WAS_MARKED;
		}

		// In a fork it's possible that a derived is executed and gets reactions, then commits, but is
		// never re-executed. This is possible when the derived is only executed once in the context
		// of a new branch which happens before fork.commit() runs. In this case, the derived still has
		// UNINITIALIZED as its value, and then when it's loosing its reactions we need to ensure it stays
		// DIRTY so it is reexecuted once someone wants its value again.
		if (derived.v !== UNINITIALIZED) {
			update_derived_status(derived);
		}

		// freeze any effects inside this derived
		freeze_derived_effects(derived);

		// Disconnect any reactions owned by this reaction
		remove_reactions(derived, 0);
	}
}

/**
 * @param {Reaction} signal
 * @param {number} start_index
 * @returns {void}
 */
function remove_reactions(signal, start_index) {
	var dependencies = signal.deps;
	if (dependencies === null) return;

	for (var i = start_index; i < dependencies.length; i++) {
		remove_reaction(signal, dependencies[i]);
	}
}

/**
 * @param {Effect} effect
 * @returns {void}
 */
function update_effect(effect) {
	var flags = effect.f;

	if ((flags & DESTROYED) !== 0) {
		return;
	}

	set_signal_status(effect, CLEAN);

	var previous_effect = active_effect;
	var was_updating_effect = is_updating_effect;

	active_effect = effect;
	is_updating_effect = true;

	if (DEV) {
		var previous_component_fn = dev_current_component_function;
		set_dev_current_component_function(effect.component_function);
		var previous_stack = /** @type {any} */ (dev_stack);
		// only block effects have a dev stack, keep the current one otherwise
		set_dev_stack(effect.dev_stack ?? dev_stack);
	}

	try {
		if ((flags & (BLOCK_EFFECT | MANAGED_EFFECT)) !== 0) {
			destroy_block_effect_children(effect);
		} else {
			destroy_effect_children(effect);
		}

		execute_effect_teardown(effect);
		var teardown = update_reaction(effect);
		effect.teardown = typeof teardown === 'function' ? teardown : null;
		effect.wv = write_version;

		// In DEV, increment versions of any sources that were written to during the effect,
		// so that they are correctly marked as dirty when the effect re-runs
		if (DEV && tracing_mode_flag && (effect.f & DIRTY) !== 0 && effect.deps !== null) {
			for (var dep of effect.deps) {
				if (dep.set_during_effect) {
					dep.wv = increment_write_version();
					dep.set_during_effect = false;
				}
			}
		}
	} finally {
		is_updating_effect = was_updating_effect;
		active_effect = previous_effect;

		if (DEV) {
			set_dev_current_component_function(previous_component_fn);
			set_dev_stack(previous_stack);
		}
	}
}

/**
 * Returns a promise that resolves once any pending state changes have been applied.
 * @returns {Promise<void>}
 */
async function tick() {

	await Promise.resolve();

	// By calling flushSync we guarantee that any pending state changes are applied after one tick.
	// TODO look into whether we can make flushing subsequent updates synchronously in the future.
	flushSync();
}

/**
 * @template V
 * @param {Value<V>} signal
 * @returns {V}
 */
function get(signal) {
	var flags = signal.f;
	var is_derived = (flags & DERIVED) !== 0;

	// Register the dependency on the current reaction signal.
	if (active_reaction !== null && !untracking) {
		// if we're in a derived that is being read inside an _async_ derived,
		// it's possible that the effect was already destroyed. In this case,
		// we don't add the dependency, because that would create a memory leak
		var destroyed = active_effect !== null && (active_effect.f & DESTROYED) !== 0;

		if (!destroyed && (current_sources === null || !includes.call(current_sources, signal))) {
			var deps = active_reaction.deps;

			if ((active_reaction.f & REACTION_IS_UPDATING) !== 0) {
				// we're in the effect init/update cycle
				if (signal.rv < read_version) {
					signal.rv = read_version;

					// If the signal is accessing the same dependencies in the same
					// order as it did last time, increment `skipped_deps`
					// rather than updating `new_deps`, which creates GC cost
					if (new_deps === null && deps !== null && deps[skipped_deps] === signal) {
						skipped_deps++;
					} else if (new_deps === null) {
						new_deps = [signal];
					} else {
						new_deps.push(signal);
					}
				}
			} else {
				// we're adding a dependency outside the init/update cycle
				// (i.e. after an `await`)
				(active_reaction.deps ??= []).push(signal);

				var reactions = signal.reactions;

				if (reactions === null) {
					signal.reactions = [active_reaction];
				} else if (!includes.call(reactions, active_reaction)) {
					reactions.push(active_reaction);
				}
			}
		}
	}

	if (DEV) {
		if (
			!untracking &&
			reactivity_loss_tracker &&
			!reactivity_loss_tracker.warned &&
			(reactivity_loss_tracker.effect.f & REACTION_IS_UPDATING) === 0
		) {
			reactivity_loss_tracker.warned = true;

			await_reactivity_loss(/** @type {string} */ (signal.label));

			var trace = get_error('traced at');
			// eslint-disable-next-line no-console
			if (trace) console.warn(trace);
		}

		recent_async_deriveds.delete(signal);
	}

	if (is_destroying_effect && old_values.has(signal)) {
		return old_values.get(signal);
	}

	if (is_derived) {
		var derived = /** @type {Derived} */ (signal);

		if (is_destroying_effect) {
			var value = derived.v;

			// if the derived is dirty and has reactions, or depends on the values that just changed, re-execute
			// (a derived can be maybe_dirty due to the effect destroy removing its last reaction)
			if (
				((derived.f & CLEAN) === 0 && derived.reactions !== null) ||
				depends_on_old_values(derived)
			) {
				value = execute_derived(derived);
			}

			old_values.set(derived, value);

			return value;
		}

		// connect disconnected deriveds if we are reading them inside an effect,
		// or inside another derived that is already connected
		var should_connect =
			(derived.f & CONNECTED) === 0 &&
			!untracking &&
			active_reaction !== null &&
			(is_updating_effect || (active_reaction.f & CONNECTED) !== 0);

		var is_new = (derived.f & REACTION_RAN) === 0;

		if (is_dirty(derived)) {
			if (should_connect) {
				// set the flag before `update_derived`, so that the derived
				// is added as a reaction to its dependencies
				derived.f |= CONNECTED;
			}

			update_derived(derived);
		}

		if (should_connect && !is_new) {
			unfreeze_derived_effects(derived);
			reconnect(derived);
		}
	}

	if (batch_values?.has(signal)) {
		return batch_values.get(signal);
	}

	if ((signal.f & ERROR_VALUE) !== 0) {
		throw signal.v;
	}

	return signal.v;
}

/**
 * (Re)connect a disconnected derived, so that it is notified
 * of changes in `mark_reactions`
 * @param {Derived} derived
 */
function reconnect(derived) {
	derived.f |= CONNECTED;

	if (derived.deps === null) return;

	for (const dep of derived.deps) {
		(dep.reactions ??= []).push(derived);

		if ((dep.f & DERIVED) !== 0 && (dep.f & CONNECTED) === 0) {
			unfreeze_derived_effects(/** @type {Derived} */ (dep));
			reconnect(/** @type {Derived} */ (dep));
		}
	}
}

/** @param {Derived} derived */
function depends_on_old_values(derived) {
	if (derived.v === UNINITIALIZED) return true; // we don't know, so assume the worst
	if (derived.deps === null) return false;

	for (const dep of derived.deps) {
		if (old_values.has(dep)) {
			return true;
		}

		if ((dep.f & DERIVED) !== 0 && depends_on_old_values(/** @type {Derived} */ (dep))) {
			return true;
		}
	}

	return false;
}

/**
 * When used inside a [`$derived`](https://svelte.dev/docs/svelte/$derived) or [`$effect`](https://svelte.dev/docs/svelte/$effect),
 * any state read inside `fn` will not be treated as a dependency.
 *
 * ```ts
 * $effect(() => {
 *   // this will run when `data` changes, but not when `time` changes
 *   save(data, {
 *     timestamp: untrack(() => time)
 *   });
 * });
 * ```
 * @template T
 * @param {() => T} fn
 * @returns {T}
 */
function untrack(fn) {
	var previous_untracking = untracking;
	try {
		untracking = true;
		return fn();
	} finally {
		untracking = previous_untracking;
	}
}

/**
 * Possibly traverse an object and read all its properties so that they're all reactive in case this is `$state`.
 * Does only check first level of an object for performance reasons (heuristic should be good for 99% of all cases).
 * @param {any} value
 * @returns {void}
 */
function deep_read_state(value) {
	if (typeof value !== 'object' || !value || value instanceof EventTarget) {
		return;
	}

	if (STATE_SYMBOL in value) {
		deep_read(value);
	} else if (!Array.isArray(value)) {
		for (let key in value) {
			const prop = value[key];
			if (typeof prop === 'object' && prop && STATE_SYMBOL in prop) {
				deep_read(prop);
			}
		}
	}
}

/**
 * Deeply traverse an object and read all its properties
 * so that they're all reactive in case this is `$state`
 * @param {any} value
 * @param {Set<any>} visited
 * @returns {void}
 */
function deep_read(value, visited = new Set()) {
	if (
		typeof value === 'object' &&
		value !== null &&
		// We don't want to traverse DOM elements
		!(value instanceof EventTarget) &&
		!visited.has(value)
	) {
		visited.add(value);
		// When working with a possible SvelteDate, this
		// will ensure we capture changes to it.
		if (value instanceof Date) {
			value.getTime();
		}
		for (let key in value) {
			try {
				deep_read(value[key], visited);
			} catch (e) {
				// continue
			}
		}
		const proto = get_prototype_of(value);
		if (
			proto !== Object.prototype &&
			proto !== Array.prototype &&
			proto !== Map.prototype &&
			proto !== Set.prototype &&
			proto !== Date.prototype
		) {
			const descriptors = get_descriptors(proto);
			for (let key in descriptors) {
				const get = descriptors[key].get;
				if (get) {
					try {
						get.call(value);
					} catch (e) {
						// continue
					}
				}
			}
		}
	}
}

/**
 * Subset of delegated events which should be passive by default.
 * These two are already passive via browser defaults on window, document and body.
 * But since
 * - we're delegating them
 * - they happen often
 * - they apply to mobile which is generally less performant
 * we're marking them as passive by default for other elements, too.
 */
const PASSIVE_EVENTS = ['touchstart', 'touchmove'];

/**
 * Returns `true` if `name` is a passive event
 * @param {string} name
 */
function is_passive_event(name) {
	return PASSIVE_EVENTS.includes(name);
}

/**
 * Prevent devtools trying to make `location` a clickable link by inserting a zero-width space
 * @template {string | undefined} T
 * @param {T} location
 * @returns {T};
 */
function sanitize_location(location) {
	return /** @type {T} */ (location?.replace(/\//g, '/\u200b'));
}

/** @import { SourceLocation } from '#client' */

/**
 * @param {any} fn
 * @param {string} filename
 * @param {SourceLocation[]} locations
 * @returns {any}
 */
function add_locations(fn, filename, locations) {
	return (/** @type {any[]} */ ...args) => {
		const dom = fn(...args);

		var node = dom.nodeType === DOCUMENT_FRAGMENT_NODE ? dom.firstChild : dom;
		assign_locations(node, filename, locations);

		return dom;
	};
}

/**
 * @param {Element} element
 * @param {string} filename
 * @param {SourceLocation} location
 */
function assign_location(element, filename, location) {
	// @ts-expect-error
	element.__svelte_meta = {
		parent: dev_stack,
		loc: { file: filename, line: location[0], column: location[1] }
	};

	if (location[2]) {
		assign_locations(element.firstChild, filename, location[2]);
	}
}

/**
 * @param {Node | null} node
 * @param {string} filename
 * @param {SourceLocation[]} locations
 */
function assign_locations(node, filename, locations) {
	var i = 0;

	while (node && i < locations.length) {

		if (node.nodeType === ELEMENT_NODE) {
			assign_location(/** @type {Element} */ (node), filename, locations[i++]);
		}

		node = node.nextSibling;
	}
}

/**
 * Used on elements, as a map of event type -> event handler,
 * and on events themselves to track which element handled an event
 */
const event_symbol = Symbol('events');

/** @type {Set<string>} */
const all_registered_events = new Set();

/** @type {Set<(events: Array<string>) => void>} */
const root_event_handles = new Set();

/**
 * @param {string} event_name
 * @param {EventTarget} dom
 * @param {EventListener} [handler]
 * @param {AddEventListenerOptions} [options]
 */
function create_event(event_name, dom, handler, options = {}) {
	/**
	 * @this {EventTarget}
	 */
	function target_handler(/** @type {Event} */ event) {
		if (!options.capture) {
			// Only call in the bubble phase, else delegated events would be called before the capturing events
			handle_event_propagation.call(dom, event);
		}
		if (!event.cancelBubble) {
			return without_reactive_context(() => {
				return handler?.call(this, event);
			});
		}
	}

	// Chrome has a bug where pointer events don't work when attached to a DOM element that has been cloned
	// with cloneNode() and the DOM element is disconnected from the document. To ensure the event works, we
	// defer the attachment till after it's been appended to the document. TODO: remove this once Chrome fixes
	// this bug. The same applies to wheel events and touch events.
	if (
		event_name.startsWith('pointer') ||
		event_name.startsWith('touch') ||
		event_name === 'wheel'
	) {
		queue_micro_task(() => {
			dom.addEventListener(event_name, target_handler, options);
		});
	} else {
		dom.addEventListener(event_name, target_handler, options);
	}

	return target_handler;
}

/**
 * @param {string} event_name
 * @param {Element} dom
 * @param {EventListener} [handler]
 * @param {boolean} [capture]
 * @param {boolean} [passive]
 * @returns {void}
 */
function event(event_name, dom, handler, capture, passive) {
	var options = { capture, passive };
	var target_handler = create_event(event_name, dom, handler, options);

	if (
		dom === document.body ||
		// @ts-ignore
		dom === window ||
		// @ts-ignore
		dom === document ||
		// Firefox has quirky behavior, it can happen that we still get "canplay" events when the element is already removed
		dom instanceof HTMLMediaElement
	) {
		teardown(() => {
			dom.removeEventListener(event_name, target_handler, options);
		});
	}
}

/**
 * @param {string} event_name
 * @param {Element} element
 * @param {EventListener} [handler]
 * @returns {void}
 */
function delegated(event_name, element, handler) {
	// @ts-expect-error
	(element[event_symbol] ??= {})[event_name] = handler;
}

/**
 * @param {Array<string>} events
 * @returns {void}
 */
function delegate(events) {
	for (var i = 0; i < events.length; i++) {
		all_registered_events.add(events[i]);
	}

	for (var fn of root_event_handles) {
		fn(events);
	}
}

// used to store the reference to the currently propagated event
// to prevent garbage collection between microtasks in Firefox
// If the event object is GCed too early, the expando __root property
// set on the event object is lost, causing the event delegation
// to process the event twice
let last_propagated_event = null;

/**
 * @this {EventTarget}
 * @param {Event} event
 * @returns {void}
 */
function handle_event_propagation(event) {
	var handler_element = this;
	var owner_document = /** @type {Node} */ (handler_element).ownerDocument;
	var event_name = event.type;
	var path = event.composedPath?.() || [];
	var current_target = /** @type {null | Element} */ (path[0] || event.target);

	last_propagated_event = event;

	// composedPath contains list of nodes the event has propagated through.
	// We check `event_symbol` to skip all nodes below it in case this is a
	// parent of the `event_symbol` node, which indicates that there's nested
	// mounted apps. In this case we don't want to trigger events multiple times.
	var path_idx = 0;

	// the `last_propagated_event === event` check is redundant, but
	// without it the variable will be DCE'd and things will
	// fail mysteriously in Firefox
	// @ts-expect-error is added below
	var handled_at = last_propagated_event === event && event[event_symbol];

	if (handled_at) {
		var at_idx = path.indexOf(handled_at);
		if (
			at_idx !== -1 &&
			(handler_element === document || handler_element === /** @type {any} */ (window))
		) {
			// This is the fallback document listener or a window listener, but the event was already handled
			// -> ignore, but set handle_at to document/window so that we're resetting the event
			// chain in case someone manually dispatches the same event object again.
			// @ts-expect-error
			event[event_symbol] = handler_element;
			return;
		}

		// We're deliberately not skipping if the index is higher, because
		// someone could create an event programmatically and emit it multiple times,
		// in which case we want to handle the whole propagation chain properly each time.
		// (this will only be a false negative if the event is dispatched multiple times and
		// the fallback document listener isn't reached in between, but that's super rare)
		var handler_idx = path.indexOf(handler_element);
		if (handler_idx === -1) {
			// handle_idx can theoretically be -1 (happened in some JSDOM testing scenarios with an event listener on the window object)
			// so guard against that, too, and assume that everything was handled at this point.
			return;
		}

		if (at_idx <= handler_idx) {
			path_idx = at_idx;
		}
	}

	current_target = /** @type {Element} */ (path[path_idx] || event.target);
	// there can only be one delegated event per element, and we either already handled the current target,
	// or this is the very first target in the chain which has a non-delegated listener, in which case it's safe
	// to handle a possible delegated event on it later (through the root delegation listener for example).
	if (current_target === handler_element) return;

	// Proxy currentTarget to correct target
	define_property(event, 'currentTarget', {
		configurable: true,
		get() {
			return current_target || owner_document;
		}
	});

	// This started because of Chromium issue https://chromestatus.com/feature/5128696823545856,
	// where removal or moving of of the DOM can cause sync `blur` events to fire, which can cause logic
	// to run inside the current `active_reaction`, which isn't what we want at all. However, on reflection,
	// it's probably best that all event handled by Svelte have this behaviour, as we don't really want
	// an event handler to run in the context of another reaction or effect.
	var previous_reaction = active_reaction;
	var previous_effect = active_effect;
	set_active_reaction(null);
	set_active_effect(null);

	try {
		/**
		 * @type {unknown}
		 */
		var throw_error;
		/**
		 * @type {unknown[]}
		 */
		var other_errors = [];

		while (current_target !== null) {
			/** @type {null | Element} */
			var parent_element =
				current_target.assignedSlot ||
				current_target.parentNode ||
				/** @type {any} */ (current_target).host ||
				null;

			try {
				// @ts-expect-error
				var delegated = current_target[event_symbol]?.[event_name];

				if (
					delegated != null &&
					(!(/** @type {any} */ (current_target).disabled) ||
						// DOM could've been updated already by the time this is reached, so we check this as well
						// -> the target could not have been disabled because it emits the event in the first place
						event.target === current_target)
				) {
					delegated.call(current_target, event);
				}
			} catch (error) {
				if (throw_error) {
					other_errors.push(error);
				} else {
					throw_error = error;
				}
			}
			if (event.cancelBubble || parent_element === handler_element || parent_element === null) {
				break;
			}
			current_target = parent_element;
		}

		if (throw_error) {
			for (let error of other_errors) {
				// Throw the rest of the errors, one-by-one on a microtask
				queueMicrotask(() => {
					throw error;
				});
			}
			throw throw_error;
		}
	} finally {
		// @ts-expect-error is used above
		event[event_symbol] = handler_element;
		// @ts-ignore remove proxy on currentTarget
		delete event.currentTarget;
		set_active_reaction(previous_reaction);
		set_active_effect(previous_effect);
	}
}

/**
 * In dev, warn if an event handler is not a function, as it means the
 * user probably called the handler or forgot to add a `() =>`
 * @param {() => (event: Event, ...args: any) => void} thunk
 * @param {EventTarget} element
 * @param {[Event, ...any]} args
 * @param {any} component
 * @param {[number, number]} [loc]
 * @param {boolean} [remove_parens]
 */
function apply(
	thunk,
	element,
	args,
	component,
	loc,
	has_side_effects = false,
	remove_parens = false
) {
	let handler;
	let error;

	try {
		handler = thunk();
	} catch (e) {
		error = e;
	}

	if (typeof handler !== 'function' && (has_side_effects || handler != null || error)) {
		const filename = component?.[FILENAME];
		const location = loc ? ` at ${filename}:${loc[0]}:${loc[1]}` : ` in ${filename}`;
		const phase = args[0]?.eventPhase < Event.BUBBLING_PHASE ? 'capture' : '';
		const event_name = args[0]?.type + phase;
		const description = `\`${event_name}\` handler${location}`;
		const suggestion = remove_parens ? 'remove the trailing `()`' : 'add a leading `() =>`';

		event_handler_invalid(description, suggestion);

		if (error) {
			throw error;
		}
	}
	handler?.apply(element, args);
}

const policy =
	// We gotta write it like this because after downleveling the pure comment may end up in the wrong location
	globalThis?.window?.trustedTypes &&
	/* @__PURE__ */ globalThis.window.trustedTypes.createPolicy('svelte-trusted-html', {
		/** @param {string} html */
		createHTML: (html) => {
			return html;
		}
	});

/** @param {string} html */
function create_trusted_html(html) {
	return /** @type {string} */ (policy?.createHTML(html) ?? html);
}

/**
 * @param {string} html
 */
function create_fragment_from_html(html) {
	var elem = create_element('template');
	elem.innerHTML = create_trusted_html(html.replaceAll('<!>', '<!---->')); // XHTML compliance
	return elem.content;
}

/** @import { Effect, EffectNodes, TemplateNode } from '#client' */
/** @import { TemplateStructure } from './types' */

/**
 * @param {TemplateNode} start
 * @param {TemplateNode | null} end
 */
function assign_nodes(start, end) {
	var effect = /** @type {Effect} */ (active_effect);
	if (effect.nodes === null) {
		effect.nodes = { start, end, a: null, t: null };
	}
}

/**
 * @param {string} content
 * @param {number} flags
 * @returns {() => Node | Node[]}
 */
/*#__NO_SIDE_EFFECTS__*/
function from_html(content, flags) {
	var is_fragment = (flags & TEMPLATE_FRAGMENT) !== 0;
	var use_import_node = (flags & TEMPLATE_USE_IMPORT_NODE) !== 0;

	/** @type {Node} */
	var node;

	/**
	 * Whether or not the first item is a text/element node. If not, we need to
	 * create an additional comment node to act as `effect.nodes.start`
	 */
	var has_start = !content.startsWith('<!>');

	return () => {

		if (node === undefined) {
			node = create_fragment_from_html(has_start ? content : '<!>' + content);
			if (!is_fragment) node = /** @type {TemplateNode} */ (get_first_child(node));
		}

		var clone = /** @type {TemplateNode} */ (
			use_import_node || is_firefox ? document.importNode(node, true) : node.cloneNode(true)
		);

		if (is_fragment) {
			var start = /** @type {TemplateNode} */ (get_first_child(clone));
			var end = /** @type {TemplateNode} */ (clone.lastChild);

			assign_nodes(start, end);
		} else {
			assign_nodes(clone, clone);
		}

		return clone;
	};
}

/**
 * Don't mark this as side-effect-free, hydration needs to walk all nodes
 * @param {any} value
 */
function text(value = '') {
	{
		var t = create_text(value + '');
		assign_nodes(t, t);
		return t;
	}
}

/**
 * @returns {TemplateNode | DocumentFragment}
 */
function comment() {

	var frag = document.createDocumentFragment();
	var start = document.createComment('');
	var anchor = create_text();
	frag.append(start, anchor);

	assign_nodes(start, anchor);

	return frag;
}

/**
 * Assign the created (or in hydration mode, traversed) dom elements to the current block
 * and insert the elements into the dom (in client mode).
 * @param {Text | Comment | Element} anchor
 * @param {DocumentFragment | Element} dom
 */
function append(anchor, dom) {

	if (anchor === null) {
		// edge case — void `<svelte:element>` with content
		return;
	}

	anchor.before(/** @type {Node} */ (dom));
}

/** @import { ComponentContext, Effect, EffectNodes, TemplateNode } from '#client' */
/** @import { Component, ComponentType, SvelteComponent, MountOptions } from '../../index.js' */

/**
 * This is normally true — block effects should run their intro transitions —
 * but is false during hydration (unless `options.intro` is `true`) and
 * when creating the children of a `<svelte:element>` that just changed tag
 */
let should_intro = true;

/**
 * @param {Element} text
 * @param {string} value
 * @returns {void}
 */
function set_text(text, value) {
	// For objects, we apply string coercion (which might make things like $state array references in the template reactive) before diffing
	var str = value == null ? '' : typeof value === 'object' ? `${value}` : value;
	// @ts-expect-error
	if (str !== (text.__t ??= text.nodeValue)) {
		// @ts-expect-error
		text.__t = str;
		text.nodeValue = `${str}`;
	}
}

/**
 * Mounts a component to the given target and returns the exports and potentially the props (if compiled with `accessors: true`) of the component.
 * Transitions will play during the initial render unless the `intro` option is set to `false`.
 *
 * @template {Record<string, any>} Props
 * @template {Record<string, any>} Exports
 * @param {ComponentType<SvelteComponent<Props>> | Component<Props, Exports, any>} component
 * @param {MountOptions<Props>} options
 * @returns {Exports}
 */
function mount(component, options) {
	return _mount(component, options);
}

/** @type {Map<EventTarget, Map<string, number>>} */
const listeners = new Map();

/**
 * @template {Record<string, any>} Exports
 * @param {ComponentType<SvelteComponent<any>> | Component<any>} Component
 * @param {MountOptions} options
 * @returns {Exports}
 */
function _mount(
	Component,
	{ target, anchor, props = {}, events, context, intro = true, transformError }
) {
	init_operations();

	/** @type {Exports} */
	// @ts-expect-error will be defined because the render effect runs synchronously
	var component = undefined;

	var unmount = component_root(() => {
		var anchor_node = anchor ?? target.appendChild(create_text());

		boundary(
			/** @type {TemplateNode} */ (anchor_node),
			{
				pending: () => {}
			},
			(anchor_node) => {
				push$1({});
				var ctx = /** @type {ComponentContext} */ (component_context);
				if (context) ctx.c = context;

				if (events) {
					// We can't spread the object or else we'd lose the state proxy stuff, if it is one
					/** @type {any} */ (props).$$events = events;
				}

				should_intro = intro;
				// @ts-expect-error the public typings are not what the actual function looks like
				component = Component(anchor_node, props) || {};
				should_intro = true;

				pop();
			},
			transformError
		);

		// Setup event delegation _after_ component is mounted - if an error would happen during mount, it would otherwise not be cleaned up
		/** @type {Set<string>} */
		var registered_events = new Set();

		/** @param {Array<string>} events */
		var event_handle = (events) => {
			for (var i = 0; i < events.length; i++) {
				var event_name = events[i];

				if (registered_events.has(event_name)) continue;
				registered_events.add(event_name);

				var passive = is_passive_event(event_name);

				// Add the event listener to both the container and the document.
				// The container listener ensures we catch events from within in case
				// the outer content stops propagation of the event.
				//
				// The document listener ensures we catch events that originate from elements that were
				// manually moved outside of the container (e.g. via manual portals).
				for (const node of [target, document]) {
					var counts = listeners.get(node);

					if (counts === undefined) {
						counts = new Map();
						listeners.set(node, counts);
					}

					var count = counts.get(event_name);

					if (count === undefined) {
						node.addEventListener(event_name, handle_event_propagation, { passive });
						counts.set(event_name, 1);
					} else {
						counts.set(event_name, count + 1);
					}
				}
			}
		};

		event_handle(array_from(all_registered_events));
		root_event_handles.add(event_handle);

		return () => {
			for (var event_name of registered_events) {
				for (const node of [target, document]) {
					var counts = /** @type {Map<string, number>} */ (listeners.get(node));
					var count = /** @type {number} */ (counts.get(event_name));

					if (--count == 0) {
						node.removeEventListener(event_name, handle_event_propagation);
						counts.delete(event_name);

						if (counts.size === 0) {
							listeners.delete(node);
						}
					} else {
						counts.set(event_name, count);
					}
				}
			}

			root_event_handles.delete(event_handle);

			if (anchor_node !== anchor) {
				anchor_node.parentNode?.removeChild(anchor_node);
			}
		};
	});

	mounted_components.set(component, unmount);
	return component;
}

/**
 * References of the components that were mounted or hydrated.
 * Uses a `WeakMap` to avoid memory leaks.
 */
let mounted_components = new WeakMap();

/** @typedef {{ file: string, line: number, column: number }} Location */


/**
 * Sets up a validator that
 * - traverses the path of a prop to find out if it is allowed to be mutated
 * - checks that the binding chain is not interrupted
 * @param {Record<string, any>} props
 */
function create_ownership_validator(props) {
	const component = component_context?.function;
	const parent = component_context?.p?.function;

	return {
		/**
		 * @param {string} prop
		 * @param {any[]} path
		 * @param {any} result
		 * @param {number} line
		 * @param {number} column
		 */
		mutation: (prop, path, result, line, column) => {
			const name = path[0];
			if (is_bound_or_unset(props, name) || !parent) {
				return result;
			}

			/** @type {any} */
			let value = props;

			for (let i = 0; i < path.length - 1; i++) {
				value = value[path[i]];
				if (!value?.[STATE_SYMBOL]) {
					return result;
				}
			}

			const location = sanitize_location(`${component[FILENAME]}:${line}:${column}`);

			ownership_invalid_mutation(name, location, prop, parent[FILENAME]);

			return result;
		},
		/**
		 * @param {any} key
		 * @param {any} child_component
		 * @param {() => any} value
		 */
		binding: (key, child_component, value) => {
			if (!is_bound_or_unset(props, key) && parent && value()?.[STATE_SYMBOL]) {
				ownership_invalid_binding(
					component[FILENAME],
					key,
					child_component[FILENAME],
					parent[FILENAME]
				);
			}
		}
	};
}

/**
 * @param {Record<string, any>} props
 * @param {string} prop_name
 */
function is_bound_or_unset(props, prop_name) {
	// Can be the case when someone does `mount(Component, props)` with `let props = $state({...})`
	// or `createClassComponent(Component, props)`
	const is_entry_props = STATE_SYMBOL in props || LEGACY_PROPS in props;
	return (
		!!get_descriptor(props, prop_name)?.set ||
		(is_entry_props && prop_name in props) ||
		!(prop_name in props)
	);
}

/** @param {Function & { [FILENAME]: string }} target */
function check_target(target) {
	if (target) {
		component_api_invalid_new(target[FILENAME] ?? 'a component', target.name);
	}
}

function legacy_api() {
	const component = component_context?.function;

	/** @param {string} method */
	function error(method) {
		component_api_changed(method, component[FILENAME]);
	}

	return {
		$destroy: () => error('$destroy()'),
		$on: () => error('$on(...)'),
		$set: () => error('$set(...)')
	};
}

/**
 * @param {Node} anchor
 * @param {...(()=>any)[]} args
 */
function validate_snippet_args(anchor, ...args) {
	if (typeof anchor !== 'object' || !(anchor instanceof Node)) {
		invalid_snippet_arguments();
	}

	for (let arg of args) {
		if (typeof arg !== 'function') {
			invalid_snippet_arguments();
		}
	}
}

/** @import { Effect, TemplateNode } from '#client' */

/**
 * @typedef {{ effect: Effect, fragment: DocumentFragment }} Branch
 */

/**
 * @template Key
 */
class BranchManager {
	/** @type {TemplateNode} */
	anchor;

	/** @type {Map<Batch, Key>} */
	#batches = new Map();

	/**
	 * Map of keys to effects that are currently rendered in the DOM.
	 * These effects are visible and actively part of the document tree.
	 * Example:
	 * ```
	 * {#if condition}
	 * 	foo
	 * {:else}
	 * 	bar
	 * {/if}
	 * ```
	 * Can result in the entries `true->Effect` and `false->Effect`
	 * @type {Map<Key, Effect>}
	 */
	#onscreen = new Map();

	/**
	 * Similar to #onscreen with respect to the keys, but contains branches that are not yet
	 * in the DOM, because their insertion is deferred.
	 * @type {Map<Key, Branch>}
	 */
	#offscreen = new Map();

	/**
	 * Keys of effects that are currently outroing
	 * @type {Set<Key>}
	 */
	#outroing = new Set();

	/**
	 * Whether to pause (i.e. outro) on change, or destroy immediately.
	 * This is necessary for `<svelte:element>`
	 */
	#transition = true;

	/**
	 * @param {TemplateNode} anchor
	 * @param {boolean} transition
	 */
	constructor(anchor, transition = true) {
		this.anchor = anchor;
		this.#transition = transition;
	}

	/**
	 * @param {Batch} batch
	 */
	#commit = (batch) => {
		// if this batch was made obsolete, bail
		if (!this.#batches.has(batch)) return;

		var key = /** @type {Key} */ (this.#batches.get(batch));

		var onscreen = this.#onscreen.get(key);

		if (onscreen) {
			// effect is already in the DOM — abort any current outro
			resume_effect(onscreen);
			this.#outroing.delete(key);
		} else {
			// effect is currently offscreen. put it in the DOM
			var offscreen = this.#offscreen.get(key);

			if (offscreen) {
				this.#onscreen.set(key, offscreen.effect);
				this.#offscreen.delete(key);

				// remove the anchor...
				/** @type {TemplateNode} */ (offscreen.fragment.lastChild).remove();

				// ...and append the fragment
				this.anchor.before(offscreen.fragment);
				onscreen = offscreen.effect;
			}
		}

		for (const [b, k] of this.#batches) {
			this.#batches.delete(b);

			if (b === batch) {
				// keep values for newer batches
				break;
			}

			const offscreen = this.#offscreen.get(k);

			if (offscreen) {
				// for older batches, destroy offscreen effects
				// as they will never be committed
				destroy_effect(offscreen.effect);
				this.#offscreen.delete(k);
			}
		}

		// outro/destroy all onscreen effects...
		for (const [k, effect] of this.#onscreen) {
			// ...except the one that was just committed
			//    or those that are already outroing (else the transition is aborted and the effect destroyed right away)
			if (k === key || this.#outroing.has(k)) continue;

			const on_destroy = () => {
				const keys = Array.from(this.#batches.values());

				if (keys.includes(k)) {
					// keep the effect offscreen, as another batch will need it
					var fragment = document.createDocumentFragment();
					move_effect(effect, fragment);

					fragment.append(create_text()); // TODO can we avoid this?

					this.#offscreen.set(k, { effect, fragment });
				} else {
					destroy_effect(effect);
				}

				this.#outroing.delete(k);
				this.#onscreen.delete(k);
			};

			if (this.#transition || !onscreen) {
				this.#outroing.add(k);
				pause_effect(effect, on_destroy, false);
			} else {
				on_destroy();
			}
		}
	};

	/**
	 * @param {Batch} batch
	 */
	#discard = (batch) => {
		this.#batches.delete(batch);

		const keys = Array.from(this.#batches.values());

		for (const [k, branch] of this.#offscreen) {
			if (!keys.includes(k)) {
				destroy_effect(branch.effect);
				this.#offscreen.delete(k);
			}
		}
	};

	/**
	 *
	 * @param {any} key
	 * @param {null | ((target: TemplateNode) => void)} fn
	 */
	ensure(key, fn) {
		var batch = /** @type {Batch} */ (current_batch);
		var defer = should_defer_append();

		if (fn && !this.#onscreen.has(key) && !this.#offscreen.has(key)) {
			if (defer) {
				var fragment = document.createDocumentFragment();
				var target = create_text();

				fragment.append(target);

				this.#offscreen.set(key, {
					effect: branch(() => fn(target)),
					fragment
				});
			} else {
				this.#onscreen.set(
					key,
					branch(() => fn(this.anchor))
				);
			}
		}

		this.#batches.set(batch, key);

		if (defer) {
			for (const [k, effect] of this.#onscreen) {
				if (k === key) {
					batch.unskip_effect(effect);
				} else {
					batch.skip_effect(effect);
				}
			}

			for (const [k, branch] of this.#offscreen) {
				if (k === key) {
					batch.unskip_effect(branch.effect);
				} else {
					batch.skip_effect(branch.effect);
				}
			}

			batch.oncommit(this.#commit);
			batch.ondiscard(this.#discard);
		} else {

			this.#commit(batch);
		}
	}
}

/** @import { Source, TemplateNode } from '#client' */

const PENDING = 0;
const THEN = 1;
const CATCH = 2;

/** @typedef {typeof PENDING | typeof THEN | typeof CATCH} AwaitState */

/**
 * @template V
 * @param {TemplateNode} node
 * @param {(() => any)} get_input
 * @param {null | ((anchor: Node) => void)} pending_fn
 * @param {null | ((anchor: Node, value: Source<V>) => void)} then_fn
 * @param {null | ((anchor: Node, error: unknown) => void)} catch_fn
 * @returns {void}
 */
function await_block(node, get_input, pending_fn, then_fn, catch_fn) {

	var runes = is_runes();

	var v = /** @type {V} */ (UNINITIALIZED);
	var value = runes ? source(v) : mutable_source(v, false, false);
	var error = runes ? source(v) : mutable_source(v, false, false);

	var branches = new BranchManager(node);

	block(() => {
		var batch = /** @type {Batch} */ (current_batch);

		// we null out `current_batch` because otherwise `save(...)` will incorrectly restore it —
		// the batch will already have been committed by the time it resolves
		batch.deactivate();
		var input = get_input();
		batch.activate();

		var destroyed = false;

		if (is_promise(input)) {
			var restore = capture();
			var resolved = false;

			/**
			 * @param {() => void} fn
			 */
			const resolve = (fn) => {
				if (destroyed) return;

				resolved = true;
				// We don't want to restore the previous batch here; {#await} blocks don't follow the async logic
				// we have elsewhere, instead pending/resolve/fail states are each their own batch so to speak.
				restore(false);
				// Make sure we have a batch, since the branch manager expects one to exist
				Batch.ensure();

				try {
					fn();
				} finally {
					unset_context(false);

					// without this, the DOM does not update until two ticks after the promise
					// resolves, which is unexpected behaviour (and somewhat irksome to test)
					if (!is_flushing_sync) flushSync();
				}
			};

			input.then(
				(v) => {
					resolve(() => {
						internal_set(value, v);
						branches.ensure(THEN, then_fn && ((target) => then_fn(target, value)));
					});
				},
				(e) => {
					resolve(() => {
						internal_set(error, e);
						branches.ensure(CATCH, catch_fn && ((target) => catch_fn(target, error)));

						if (!catch_fn) {
							// Rethrow the error if no catch block exists
							throw error.v;
						}
					});
				}
			);

			{
				// Wait a microtask before checking if we should show the pending state as
				// the promise might have resolved by then
				queue_micro_task(() => {
					if (!resolved) {
						resolve(() => {
							branches.ensure(PENDING, pending_fn);
						});
					}
				});
			}
		} else {
			internal_set(value, input);
			branches.ensure(THEN, then_fn && ((target) => then_fn(target, value)));
		}

		return () => {
			destroyed = true;
		};
	});
}

/** @import { TemplateNode } from '#client' */

/**
 * @param {TemplateNode} node
 * @param {(branch: (fn: (anchor: Node) => void, key?: number | false) => void) => void} fn
 * @param {boolean} [elseif] True if this is an `{:else if ...}` block rather than an `{#if ...}`, as that affects which transitions are considered 'local'
 * @returns {void}
 */
function if_block(node, fn, elseif = false) {

	var branches = new BranchManager(node);
	var flags = elseif ? EFFECT_TRANSPARENT : 0;

	/**
	 * @param {number | false} key
	 * @param {null | ((anchor: Node) => void)} fn
	 */
	function update_branch(key, fn) {

		branches.ensure(key, fn);
	}

	block(() => {
		var has_branch = false;

		fn((fn, key = 0) => {
			has_branch = true;
			update_branch(key, fn);
		});

		if (!has_branch) {
			update_branch(-1, null);
		}
	}, flags);
}

/** @import { EachItem, EachOutroGroup, EachState, Effect, EffectNodes, MaybeSource, Source, TemplateNode, TransitionManager, Value } from '#client' */
/** @import { Batch } from '../../reactivity/batch.js'; */

// When making substantive changes to this file, validate them with the each block stress test:
// https://svelte.dev/playground/1972b2cf46564476ad8c8c6405b23b7b
// This test also exists in this repo, as `packages/svelte/tests/manual/each-stress-test`

/**
 * @param {any} _
 * @param {number} i
 */
function index(_, i) {
	return i;
}

/**
 * Pause multiple effects simultaneously, and coordinate their
 * subsequent destruction. Used in each blocks
 * @param {EachState} state
 * @param {Effect[]} to_destroy
 * @param {null | Node} controlled_anchor
 */
function pause_effects(state, to_destroy, controlled_anchor) {
	/** @type {TransitionManager[]} */
	var transitions = [];
	var length = to_destroy.length;

	/** @type {EachOutroGroup} */
	var group;
	var remaining = to_destroy.length;

	for (var i = 0; i < length; i++) {
		let effect = to_destroy[i];

		pause_effect(
			effect,
			() => {
				if (group) {
					group.pending.delete(effect);
					group.done.add(effect);

					if (group.pending.size === 0) {
						var groups = /** @type {Set<EachOutroGroup>} */ (state.outrogroups);

						destroy_effects(state, array_from(group.done));
						groups.delete(group);

						if (groups.size === 0) {
							state.outrogroups = null;
						}
					}
				} else {
					remaining -= 1;
				}
			},
			false
		);
	}

	if (remaining === 0) {
		// If we're in a controlled each block (i.e. the block is the only child of an
		// element), and we are removing all items, _and_ there are no out transitions,
		// we can use the fast path — emptying the element and replacing the anchor
		var fast_path = transitions.length === 0 && controlled_anchor !== null;

		if (fast_path) {
			var anchor = /** @type {Element} */ (controlled_anchor);
			var parent_node = /** @type {Element} */ (anchor.parentNode);

			clear_text_content(parent_node);
			parent_node.append(anchor);

			state.items.clear();
		}

		destroy_effects(state, to_destroy, !fast_path);
	} else {
		group = {
			pending: new Set(to_destroy),
			done: new Set()
		};

		(state.outrogroups ??= new Set()).add(group);
	}
}

/**
 * @param {EachState} state
 * @param {Effect[]} to_destroy
 * @param {boolean} remove_dom
 */
function destroy_effects(state, to_destroy, remove_dom = true) {
	/** @type {Set<Effect> | undefined} */
	var preserved_effects;

	// The loop-in-a-loop isn't ideal, but we should only hit this in relatively rare cases
	if (state.pending.size > 0) {
		preserved_effects = new Set();

		for (const keys of state.pending.values()) {
			for (const key of keys) {
				preserved_effects.add(/** @type {EachItem} */ (state.items.get(key)).e);
			}
		}
	}

	for (var i = 0; i < to_destroy.length; i++) {
		var e = to_destroy[i];

		if (preserved_effects?.has(e)) {
			e.f |= EFFECT_OFFSCREEN;

			const fragment = document.createDocumentFragment();
			move_effect(e, fragment);
		} else {
			destroy_effect(to_destroy[i], remove_dom);
		}
	}
}

/** @type {TemplateNode} */
var offscreen_anchor;

/**
 * @template V
 * @param {Element | Comment} node The next sibling node, or the parent node if this is a 'controlled' block
 * @param {number} flags
 * @param {() => V[]} get_collection
 * @param {(value: V, index: number) => any} get_key
 * @param {(anchor: Node, item: MaybeSource<V>, index: MaybeSource<number>) => void} render_fn
 * @param {null | ((anchor: Node) => void)} fallback_fn
 * @returns {void}
 */
function each(node, flags, get_collection, get_key, render_fn, fallback_fn = null) {
	var anchor = node;

	/** @type {Map<any, EachItem>} */
	var items = new Map();

	var is_controlled = (flags & EACH_IS_CONTROLLED) !== 0;

	if (is_controlled) {
		var parent_node = /** @type {Element} */ (node);

		anchor = parent_node.appendChild(create_text());
	}

	/** @type {Effect | null} */
	var fallback = null;

	// TODO: ideally we could use derived for runes mode but because of the ability
	// to use a store which can be mutated, we can't do that here as mutating a store
	// will still result in the collection array being the same from the store
	var each_array = derived_safe_equal(() => {
		var collection = get_collection();

		return is_array(collection) ? collection : collection == null ? [] : array_from(collection);
	});

	if (DEV) {
		tag(each_array, '{#each ...}');
	}

	/** @type {V[]} */
	var array;

	/** @type {Map<Batch, Set<any>>} */
	var pending = new Map();

	var first_run = true;

	/**
	 * @param {Batch} batch
	 */
	function commit(batch) {
		if ((state.effect.f & DESTROYED) !== 0) {
			return;
		}

		state.pending.delete(batch);

		state.fallback = fallback;
		reconcile(state, array, anchor, flags, get_key);

		if (fallback !== null) {
			if (array.length === 0) {
				if ((fallback.f & EFFECT_OFFSCREEN) === 0) {
					resume_effect(fallback);
				} else {
					fallback.f ^= EFFECT_OFFSCREEN;
					move(fallback, null, anchor);
				}
			} else {
				pause_effect(fallback, () => {
					// TODO only null out if no pending batch needs it,
					// otherwise re-add `fallback.fragment` and move the
					// effect into it
					fallback = null;
				});
			}
		}
	}

	/**
	 * @param {Batch} batch
	 */
	function discard(batch) {
		state.pending.delete(batch);
	}

	var effect = block(() => {
		array = /** @type {V[]} */ (get(each_array));
		var length = array.length;

		var keys = new Set();
		var batch = /** @type {Batch} */ (current_batch);
		var defer = should_defer_append();

		for (var index = 0; index < length; index += 1) {

			var value = array[index];
			var key = get_key(value, index);

			if (DEV) {
				// Check that the key function is idempotent (returns the same value when called twice)
				var key_again = get_key(value, index);
				if (key !== key_again) {
					each_key_volatile(String(index), String(key), String(key_again));
				}
			}

			var item = first_run ? null : items.get(key);

			if (item) {
				// update before reconciliation, to trigger any async updates
				if (item.v) internal_set(item.v, value);
				if (item.i) internal_set(item.i, index);

				if (defer) {
					batch.unskip_effect(item.e);
				}
			} else {
				item = create_item(
					items,
					first_run ? anchor : (offscreen_anchor ??= create_text()),
					value,
					key,
					index,
					render_fn,
					flags,
					get_collection
				);

				if (!first_run) {
					item.e.f |= EFFECT_OFFSCREEN;
				}

				items.set(key, item);
			}

			keys.add(key);
		}

		if (length === 0 && fallback_fn && !fallback) {
			if (first_run) {
				fallback = branch(() => fallback_fn(anchor));
			} else {
				fallback = branch(() => fallback_fn((offscreen_anchor ??= create_text())));
				fallback.f |= EFFECT_OFFSCREEN;
			}
		}

		if (length > keys.size) {
			if (DEV) {
				validate_each_keys(array, get_key);
			} else {
				// in prod, the additional information isn't printed, so don't bother computing it
				each_key_duplicate('', '', '');
			}
		}

		if (!first_run) {
			pending.set(batch, keys);

			if (defer) {
				for (const [key, item] of items) {
					if (!keys.has(key)) {
						batch.skip_effect(item.e);
					}
				}

				batch.oncommit(commit);
				batch.ondiscard(discard);
			} else {
				commit(batch);
			}
		}

		// When we mount the each block for the first time, the collection won't be
		// connected to this effect as the effect hasn't finished running yet and its deps
		// won't be assigned. However, it's possible that when reconciling the each block
		// that a mutation occurred and it's made the collection MAYBE_DIRTY, so reading the
		// collection again can provide consistency to the reactive graph again as the deriveds
		// will now be `CLEAN`.
		get(each_array);
	});

	/** @type {EachState} */
	var state = { effect, flags, items, pending, outrogroups: null, fallback };

	first_run = false;
}

/**
 * Skip past any non-branch effects (which could be created with `createSubscriber`, for example) to find the next branch effect
 * @param {Effect | null} effect
 * @returns {Effect | null}
 */
function skip_to_branch(effect) {
	while (effect !== null && (effect.f & BRANCH_EFFECT) === 0) {
		effect = effect.next;
	}
	return effect;
}

/**
 * Add, remove, or reorder items output by an each block as its input changes
 * @template V
 * @param {EachState} state
 * @param {Array<V>} array
 * @param {Element | Comment | Text} anchor
 * @param {number} flags
 * @param {(value: V, index: number) => any} get_key
 * @returns {void}
 */
function reconcile(state, array, anchor, flags, get_key) {
	var is_animated = (flags & EACH_IS_ANIMATED) !== 0;

	var length = array.length;
	var items = state.items;
	var current = skip_to_branch(state.effect.first);

	/** @type {undefined | Set<Effect>} */
	var seen;

	/** @type {Effect | null} */
	var prev = null;

	/** @type {undefined | Set<Effect>} */
	var to_animate;

	/** @type {Effect[]} */
	var matched = [];

	/** @type {Effect[]} */
	var stashed = [];

	/** @type {V} */
	var value;

	/** @type {any} */
	var key;

	/** @type {Effect | undefined} */
	var effect;

	/** @type {number} */
	var i;

	if (is_animated) {
		for (i = 0; i < length; i += 1) {
			value = array[i];
			key = get_key(value, i);
			effect = /** @type {EachItem} */ (items.get(key)).e;

			// offscreen == coming in now, no animation in that case,
			// else this would happen https://github.com/sveltejs/svelte/issues/17181
			if ((effect.f & EFFECT_OFFSCREEN) === 0) {
				effect.nodes?.a?.measure();
				(to_animate ??= new Set()).add(effect);
			}
		}
	}

	for (i = 0; i < length; i += 1) {
		value = array[i];
		key = get_key(value, i);

		effect = /** @type {EachItem} */ (items.get(key)).e;

		if (state.outrogroups !== null) {
			for (const group of state.outrogroups) {
				group.pending.delete(effect);
				group.done.delete(effect);
			}
		}

		if ((effect.f & INERT) !== 0) {
			resume_effect(effect);
			if (is_animated) {
				effect.nodes?.a?.unfix();
				(to_animate ??= new Set()).delete(effect);
			}
		}

		if ((effect.f & EFFECT_OFFSCREEN) !== 0) {
			effect.f ^= EFFECT_OFFSCREEN;

			if (effect === current) {
				move(effect, null, anchor);
			} else {
				var next = prev ? prev.next : current;

				if (effect === state.effect.last) {
					state.effect.last = effect.prev;
				}

				if (effect.prev) effect.prev.next = effect.next;
				if (effect.next) effect.next.prev = effect.prev;
				link$1(state, prev, effect);
				link$1(state, effect, next);

				move(effect, next, anchor);
				prev = effect;

				matched = [];
				stashed = [];

				current = skip_to_branch(prev.next);
				continue;
			}
		}

		if (effect !== current) {
			if (seen !== undefined && seen.has(effect)) {
				if (matched.length < stashed.length) {
					// more efficient to move later items to the front
					var start = stashed[0];
					var j;

					prev = start.prev;

					var a = matched[0];
					var b = matched[matched.length - 1];

					for (j = 0; j < matched.length; j += 1) {
						move(matched[j], start, anchor);
					}

					for (j = 0; j < stashed.length; j += 1) {
						seen.delete(stashed[j]);
					}

					link$1(state, a.prev, b.next);
					link$1(state, prev, a);
					link$1(state, b, start);

					current = start;
					prev = b;
					i -= 1;

					matched = [];
					stashed = [];
				} else {
					// more efficient to move earlier items to the back
					seen.delete(effect);
					move(effect, current, anchor);

					link$1(state, effect.prev, effect.next);
					link$1(state, effect, prev === null ? state.effect.first : prev.next);
					link$1(state, prev, effect);

					prev = effect;
				}

				continue;
			}

			matched = [];
			stashed = [];

			while (current !== null && current !== effect) {
				(seen ??= new Set()).add(current);
				stashed.push(current);
				current = skip_to_branch(current.next);
			}

			if (current === null) {
				continue;
			}
		}

		if ((effect.f & EFFECT_OFFSCREEN) === 0) {
			matched.push(effect);
		}

		prev = effect;
		current = skip_to_branch(effect.next);
	}

	if (state.outrogroups !== null) {
		for (const group of state.outrogroups) {
			if (group.pending.size === 0) {
				destroy_effects(state, array_from(group.done));
				state.outrogroups?.delete(group);
			}
		}

		if (state.outrogroups.size === 0) {
			state.outrogroups = null;
		}
	}

	if (current !== null || seen !== undefined) {
		/** @type {Effect[]} */
		var to_destroy = [];

		if (seen !== undefined) {
			for (effect of seen) {
				if ((effect.f & INERT) === 0) {
					to_destroy.push(effect);
				}
			}
		}

		while (current !== null) {
			// If the each block isn't inert, then inert effects are currently outroing and will be removed once the transition is finished
			if ((current.f & INERT) === 0 && current !== state.fallback) {
				to_destroy.push(current);
			}

			current = skip_to_branch(current.next);
		}

		var destroy_length = to_destroy.length;

		if (destroy_length > 0) {
			var controlled_anchor = (flags & EACH_IS_CONTROLLED) !== 0 && length === 0 ? anchor : null;

			if (is_animated) {
				for (i = 0; i < destroy_length; i += 1) {
					to_destroy[i].nodes?.a?.measure();
				}

				for (i = 0; i < destroy_length; i += 1) {
					to_destroy[i].nodes?.a?.fix();
				}
			}

			pause_effects(state, to_destroy, controlled_anchor);
		}
	}

	if (is_animated) {
		queue_micro_task(() => {
			if (to_animate === undefined) return;
			for (effect of to_animate) {
				effect.nodes?.a?.apply();
			}
		});
	}
}

/**
 * @template V
 * @param {Map<any, EachItem>} items
 * @param {Node} anchor
 * @param {V} value
 * @param {unknown} key
 * @param {number} index
 * @param {(anchor: Node, item: V | Source<V>, index: number | Value<number>, collection: () => V[]) => void} render_fn
 * @param {number} flags
 * @param {() => V[]} get_collection
 * @returns {EachItem}
 */
function create_item(items, anchor, value, key, index, render_fn, flags, get_collection) {
	var v =
		(flags & EACH_ITEM_REACTIVE) !== 0
			? (flags & EACH_ITEM_IMMUTABLE) === 0
				? mutable_source(value, false, false)
				: source(value)
			: null;

	var i = (flags & EACH_INDEX_REACTIVE) !== 0 ? source(index) : null;

	if (DEV && v) {
		// For tracing purposes, we need to link the source signal we create with the
		// collection + index so that tracing works as intended
		v.trace = () => {
			// eslint-disable-next-line @typescript-eslint/no-unused-expressions
			get_collection()[i?.v ?? index];
		};
	}

	return {
		v,
		i,
		e: branch(() => {
			render_fn(anchor, v ?? value, i ?? index, get_collection);

			return () => {
				items.delete(key);
			};
		})
	};
}

/**
 * @param {Effect} effect
 * @param {Effect | null} next
 * @param {Text | Element | Comment} anchor
 */
function move(effect, next, anchor) {
	if (!effect.nodes) return;

	var node = effect.nodes.start;
	var end = effect.nodes.end;

	var dest =
		next && (next.f & EFFECT_OFFSCREEN) === 0
			? /** @type {EffectNodes} */ (next.nodes).start
			: anchor;

	while (node !== null) {
		var next_node = /** @type {TemplateNode} */ (get_next_sibling(node));
		dest.before(node);

		if (node === end) {
			return;
		}

		node = next_node;
	}
}

/**
 * @param {EachState} state
 * @param {Effect | null} prev
 * @param {Effect | null} next
 */
function link$1(state, prev, next) {
	if (prev === null) {
		state.effect.first = next;
	} else {
		prev.next = next;
	}

	if (next === null) {
		state.effect.last = prev;
	} else {
		next.prev = prev;
	}
}

/**
 * @param {Array<any>} array
 * @param {(item: any, index: number) => string} key_fn
 * @returns {void}
 */
function validate_each_keys(array, key_fn) {
	const keys = new Map();
	const length = array.length;

	for (let i = 0; i < length; i++) {
		const key = key_fn(array[i], i);

		if (keys.has(key)) {
			const a = String(keys.get(key));
			const b = String(i);

			/** @type {string | null} */
			let k = String(key);
			if (k.startsWith('[object ')) k = null;

			each_key_duplicate(a, b, k);
		}

		keys.set(key, i);
	}
}

/** @import { Effect, TemplateNode } from '#client' */
/** @import {} from 'trusted-types' */

/**
 * @param {Element | Text | Comment} node
 * @param {() => string | TrustedHTML} get_value
 * @param {boolean} [is_controlled]
 * @param {boolean} [svg]
 * @param {boolean} [mathml]
 * @param {boolean} [skip_warning]
 * @returns {void}
 */
function html(
	node,
	get_value,
	is_controlled = false,
	svg = false,
	mathml = false,
	skip_warning = false
) {
	var anchor = node;

	/** @type {string | TrustedHTML} */
	var value = '';

	if (is_controlled) {
		var parent_node = /** @type {Element} */ (node);
	}

	template_effect(() => {
		var effect = /** @type {Effect} */ (active_effect);

		if (value === (value = get_value() ?? '')) {
			return;
		}

		if (is_controlled && !hydrating) {
			// When @html is the only child, use innerHTML directly.
			// This also handles contenteditable, where the user may delete the anchor comment.
			effect.nodes = null;
			parent_node.innerHTML = /** @type {string} */ (value);

			if (value !== '') {
				assign_nodes(
					/** @type {TemplateNode} */ (get_first_child(parent_node)),
					/** @type {TemplateNode} */ (parent_node.lastChild)
				);
			}

			return;
		}

		if (effect.nodes !== null) {
			remove_effect_dom(effect.nodes.start, /** @type {TemplateNode} */ (effect.nodes.end));
			effect.nodes = null;
		}

		if (value === '') return;

		// Don't use create_fragment_with_script_from_html here because that would mean script tags are executed.
		// @html is basically `.innerHTML = ...` and that doesn't execute scripts either due to security reasons.
		// Use a <template>, <svg>, or <math> wrapper depending on context. If value is a TrustedHTML object,
		// it will be assigned directly to innerHTML without coercion — this allows {@html policy.createHTML(...)} to work.
		var ns = svg ? NAMESPACE_SVG : mathml ? NAMESPACE_MATHML : undefined;
		var wrapper = /** @type {HTMLTemplateElement | SVGElement | MathMLElement} */ (
			create_element(svg ? 'svg' : mathml ? 'math' : 'template', ns)
		);
		wrapper.innerHTML = /** @type {any} */ (value);

		/** @type {DocumentFragment | Element} */
		var node = svg || mathml ? wrapper : /** @type {HTMLTemplateElement} */ (wrapper).content;

		assign_nodes(
			/** @type {TemplateNode} */ (get_first_child(node)),
			/** @type {TemplateNode} */ (node.lastChild)
		);

		if (svg || mathml) {
			while (get_first_child(node)) {
				anchor.before(/** @type {TemplateNode} */ (get_first_child(node)));
			}
		} else {
			anchor.before(node);
		}
	});
}

/**
 * @param {any} store
 * @param {string} name
 */
function validate_store(store, name) {
	if (store != null && typeof store.subscribe !== 'function') {
		store_invalid_shape(name);
	}
}

/**
 * @template {(...args: any[]) => unknown} T
 * @param {T} fn
 */
function prevent_snippet_stringification(fn) {
	fn.toString = () => {
		snippet_without_render_tag();
		return '';
	};
	return fn;
}

/** @import { Snippet } from 'svelte' */
/** @import { TemplateNode } from '#client' */
/** @import { Getters } from '#shared' */

/**
 * @template {(node: TemplateNode, ...args: any[]) => void} SnippetFn
 * @param {TemplateNode} node
 * @param {() => SnippetFn | null | undefined} get_snippet
 * @param {(() => any)[]} args
 * @returns {void}
 */
function snippet(node, get_snippet, ...args) {
	var branches = new BranchManager(node);

	block(() => {
		const snippet = get_snippet() ?? null;

		if (DEV && snippet == null) {
			invalid_snippet();
		}

		branches.ensure(snippet, snippet && ((anchor) => snippet(anchor, ...args)));
	}, EFFECT_TRANSPARENT);
}

/**
 * In development, wrap the snippet function so that it passes validation, and so that the
 * correct component context is set for ownership checks
 * @param {any} component
 * @param {(node: TemplateNode, ...args: any[]) => void} fn
 */
function wrap_snippet(component, fn) {
	const snippet = (/** @type {TemplateNode} */ node, /** @type {any[]} */ ...args) => {
		var previous_component_function = dev_current_component_function;
		set_dev_current_component_function(component);

		try {
			return fn(node, ...args);
		} finally {
			set_dev_current_component_function(previous_component_function);
		}
	};

	prevent_snippet_stringification(snippet);

	return snippet;
}

/** @import { TemplateNode, Dom } from '#client' */

/**
 * @template P
 * @template {(props: P) => void} C
 * @param {TemplateNode} node
 * @param {() => C} get_component
 * @param {(anchor: TemplateNode, component: C) => Dom | void} render_fn
 * @returns {void}
 */
function component(node, get_component, render_fn) {

	var branches = new BranchManager(node);

	block(() => {
		var component = get_component() ?? null;

		branches.ensure(component, component && ((target) => render_fn(target, component)));
	}, EFFECT_TRANSPARENT);
}

/** @import { Raf } from '#client' */

const now = () => performance.now() ;

/** @type {Raf} */
const raf = {
	// don't access requestAnimationFrame eagerly outside method
	// this allows basic testing of user code without JSDOM
	// bunder will eval and remove ternary when the user's app is built
	tick: /** @param {any} _ */ (_) => (requestAnimationFrame )(_),
	now: () => now(),
	tasks: new Set()
};

/** @import { TaskCallback, Task, TaskEntry } from '#client' */

// TODO move this into timing.js where it probably belongs

/**
 * @returns {void}
 */
function run_tasks() {
	// use `raf.now()` instead of the `requestAnimationFrame` callback argument, because
	// otherwise things can get wonky https://github.com/sveltejs/svelte/pull/14541
	const now = raf.now();

	raf.tasks.forEach((task) => {
		if (!task.c(now)) {
			raf.tasks.delete(task);
			task.f();
		}
	});

	if (raf.tasks.size !== 0) {
		raf.tick(run_tasks);
	}
}

/**
 * Creates a new task that runs on each raf frame
 * until it returns a falsy value or is aborted
 * @param {TaskCallback} callback
 * @returns {Task}
 */
function loop(callback) {
	/** @type {TaskEntry} */
	let task;

	if (raf.tasks.size === 0) {
		raf.tick(run_tasks);
	}

	return {
		promise: new Promise((fulfill) => {
			raf.tasks.add((task = { c: callback, f: fulfill }));
		}),
		abort() {
			raf.tasks.delete(task);
		}
	};
}

/** @import { AnimateFn, Animation, AnimationConfig, EachItem, Effect, EffectNodes, TransitionFn, TransitionManager } from '#client' */

/**
 * @param {Element} element
 * @param {'introstart' | 'introend' | 'outrostart' | 'outroend'} type
 * @returns {void}
 */
function dispatch_event(element, type) {
	without_reactive_context(() => {
		element.dispatchEvent(new CustomEvent(type));
	});
}

/**
 * Converts a property to the camel-case format expected by Element.animate(), KeyframeEffect(), and KeyframeEffect.setKeyframes().
 * @param {string} style
 * @returns {string}
 */
function css_property_to_camelcase(style) {
	// in compliance with spec
	if (style === 'float') return 'cssFloat';
	if (style === 'offset') return 'cssOffset';

	// do not rename custom @properties
	if (style.startsWith('--')) return style;

	const parts = style.split('-');
	if (parts.length === 1) return parts[0];
	return (
		parts[0] +
		parts
			.slice(1)
			.map(/** @param {any} word */ (word) => word[0].toUpperCase() + word.slice(1))
			.join('')
	);
}

/**
 * @param {string} css
 * @returns {Keyframe}
 */
function css_to_keyframe(css) {
	/** @type {Keyframe} */
	const keyframe = {};
	const parts = css.split(';');
	for (const part of parts) {
		const [property, value] = part.split(':');
		if (!property || value === undefined) break;

		const formatted_property = css_property_to_camelcase(property.trim());
		keyframe[formatted_property] = value.trim();
	}
	return keyframe;
}

/** @param {number} t */
const linear$2 = (t) => t;

/**
 * Called inside block effects as `$.transition(...)`. This creates a transition manager and
 * attaches it to the current effect — later, inside `pause_effect` and `resume_effect`, we
 * use this to create `intro` and `outro` transitions.
 * @template P
 * @param {number} flags
 * @param {HTMLElement} element
 * @param {() => TransitionFn<P | undefined>} get_fn
 * @param {(() => P) | null} get_params
 * @returns {void}
 */
function transition(flags, element, get_fn, get_params) {
	var is_intro = (flags & TRANSITION_IN) !== 0;
	var is_outro = (flags & TRANSITION_OUT) !== 0;
	var is_both = is_intro && is_outro;
	var is_global = (flags & TRANSITION_GLOBAL) !== 0;

	/** @type {'in' | 'out' | 'both'} */
	var direction = is_both ? 'both' : is_intro ? 'in' : 'out';

	/** @type {AnimationConfig | ((opts: { direction: 'in' | 'out' }) => AnimationConfig) | undefined} */
	var current_options;

	var inert = element.inert;

	/**
	 * The default overflow style, stashed so we can revert changes during the transition
	 * that are necessary to work around a Safari <18 bug
	 * TODO 6.0 remove this, if older versions of Safari have died out enough
	 */
	var overflow = element.style.overflow;

	/** @type {Animation | undefined} */
	var intro;

	/** @type {Animation | undefined} */
	var outro;

	function get_options() {
		return without_reactive_context(() => {
			// If a transition is still ongoing, we use the existing options rather than generating
			// new ones. This ensures that reversible transitions reverse smoothly, rather than
			// jumping to a new spot because (for example) a different `duration` was used
			return (current_options ??= get_fn()(element, get_params?.() ?? /** @type {P} */ ({}), {
				direction
			}));
		});
	}

	/** @type {TransitionManager} */
	var transition = {
		is_global,
		in() {
			element.inert = inert;

			if (!is_intro) {
				outro?.abort();
				outro?.reset?.();
				return;
			}

			if (!is_outro) {
				// if we intro then outro then intro again, we want to abort the first intro,
				// if it's not a bidirectional transition
				intro?.abort();
			}

			intro = animate(element, get_options(), outro, 1, () => {
				dispatch_event(element, 'introend');

				// Ensure we cancel the animation to prevent leaking
				intro?.abort();
				intro = current_options = undefined;

				element.style.overflow = overflow;
			});
		},
		out(fn) {
			if (!is_outro) {
				fn?.();
				current_options = undefined;
				return;
			}

			element.inert = true;

			outro = animate(element, get_options(), intro, 0, () => {
				dispatch_event(element, 'outroend');
				fn?.();
			});
		},
		stop: () => {
			intro?.abort();
			outro?.abort();
		}
	};

	var e = /** @type {Effect & { nodes: EffectNodes }} */ (active_effect);

	(e.nodes.t ??= []).push(transition);

	// if this is a local transition, we only want to run it if the parent (branch) effect's
	// parent (block) effect is where the state change happened. we can determine that by
	// looking at whether the block effect is currently initializing
	if (is_intro && should_intro) {
		var run = is_global;

		if (!run) {
			var block = /** @type {Effect | null} */ (e.parent);

			// skip over transparent blocks (e.g. snippets, else-if blocks)
			while (block && (block.f & EFFECT_TRANSPARENT) !== 0) {
				while ((block = block.parent)) {
					if ((block.f & BLOCK_EFFECT) !== 0) break;
				}
			}

			run = !block || (block.f & REACTION_RAN) !== 0;
		}

		if (run) {
			effect(() => {
				untrack(() => transition.in());
			});
		}
	}
}

/**
 * Animates an element, according to the provided configuration
 * @param {Element} element
 * @param {AnimationConfig | ((opts: { direction: 'in' | 'out' }) => AnimationConfig)} options
 * @param {Animation | undefined} counterpart The corresponding intro/outro to this outro/intro
 * @param {number} t2 The target `t` value — `1` for intro, `0` for outro
 * @param {(() => void)} on_finish Called after successfully completing the animation
 * @returns {Animation}
 */
function animate(element, options, counterpart, t2, on_finish) {
	var is_intro = t2 === 1;

	if (is_function(options)) {
		// In the case of a deferred transition (such as `crossfade`), `option` will be
		// a function rather than an `AnimationConfig`. We need to call this function
		// once the DOM has been updated...
		/** @type {Animation} */
		var a;
		var aborted = false;

		queue_micro_task(() => {
			if (aborted) return;
			var o = options({ direction: is_intro ? 'in' : 'out' });
			a = animate(element, o, counterpart, t2, on_finish);
		});

		// ...but we want to do so without using `async`/`await` everywhere, so
		// we return a facade that allows everything to remain synchronous
		return {
			abort: () => {
				aborted = true;
				a?.abort();
			},
			deactivate: () => a.deactivate(),
			reset: () => a.reset(),
			t: () => a.t()
		};
	}

	counterpart?.deactivate();

	if (!options?.duration && !options?.delay) {
		dispatch_event(element, is_intro ? 'introstart' : 'outrostart');
		on_finish();

		return {
			abort: noop,
			deactivate: noop,
			reset: noop,
			t: () => t2
		};
	}

	const { delay = 0, css, tick, easing = linear$2 } = options;

	var keyframes = [];

	if (is_intro && counterpart === undefined) {
		if (tick) {
			tick(0, 1); // TODO put in nested effect, to avoid interleaved reads/writes?
		}

		if (css) {
			var styles = css_to_keyframe(css(0, 1));
			keyframes.push(styles, styles);
		}
	}

	var get_t = () => 1 - t2;

	// create a dummy animation that lasts as long as the delay (but with whatever devtools
	// multiplier is in effect). in the common case that it is `0`, we keep it anyway so that
	// the CSS keyframes aren't created until the DOM is updated
	//
	// fill forwards to prevent the element from rendering without styles applied
	// see https://github.com/sveltejs/svelte/issues/14732
	var animation = element.animate(keyframes, { duration: delay, fill: 'forwards' });

	animation.onfinish = () => {
		// remove dummy animation from the stack to prevent conflict with main animation
		animation.cancel();

		dispatch_event(element, is_intro ? 'introstart' : 'outrostart');

		// for bidirectional transitions, we start from the current position,
		// rather than doing a full intro/outro
		var t1 = counterpart?.t() ?? 1 - t2;
		counterpart?.abort();

		var delta = t2 - t1;
		var duration = /** @type {number} */ (options.duration) * Math.abs(delta);
		var keyframes = [];

		if (duration > 0) {
			/**
			 * Whether or not the CSS includes `overflow: hidden`, in which case we need to
			 * add it as an inline style to work around a Safari <18 bug
			 * TODO 6.0 remove this, if possible
			 */
			var needs_overflow_hidden = false;

			if (css) {
				var n = Math.ceil(duration / (1000 / 60)); // `n` must be an integer, or we risk missing the `t2` value

				for (var i = 0; i <= n; i += 1) {
					var t = t1 + delta * easing(i / n);
					var styles = css_to_keyframe(css(t, 1 - t));
					keyframes.push(styles);

					needs_overflow_hidden ||= styles.overflow === 'hidden';
				}
			}

			if (needs_overflow_hidden) {
				/** @type {HTMLElement} */ (element).style.overflow = 'hidden';
			}

			get_t = () => {
				var time = /** @type {number} */ (
					/** @type {globalThis.Animation} */ (animation).currentTime
				);

				return t1 + delta * easing(time / duration);
			};

			if (tick) {
				loop(() => {
					if (animation.playState !== 'running') return false;

					var t = get_t();
					tick(t, 1 - t);

					return true;
				});
			}
		}

		animation = element.animate(keyframes, { duration, fill: 'forwards' });

		animation.onfinish = () => {
			get_t = () => t2;
			tick?.(t2, 1 - t2);
			on_finish();
		};
	};

	return {
		abort: () => {
			if (animation) {
				animation.cancel();
				// This prevents memory leaks in Chromium
				animation.effect = null;
				// This prevents onfinish to be launched after cancel(),
				// which can happen in some rare cases
				// see https://github.com/sveltejs/svelte/issues/13681
				animation.onfinish = noop;
			}
		},
		deactivate: () => {
			on_finish = noop;
		},
		reset: () => {
			if (t2 === 0) {
				tick?.(1, 0);
			}
		},
		t: () => get_t()
	};
}

/** @import { ActionPayload } from '#client' */

/**
 * @template P
 * @param {Element} dom
 * @param {(dom: Element, value?: P) => ActionPayload<P>} action
 * @param {() => P} [get_value]
 * @returns {void}
 */
function action(dom, action, get_value) {
	effect(() => {
		var payload = untrack(() => action(dom, get_value?.()) || {});

		if (get_value && payload?.update) {
			var inited = false;
			/** @type {P} */
			var prev = /** @type {any} */ ({}); // initialize with something so it's never equal on first run

			render_effect(() => {
				var value = get_value();

				// Action's update method is coarse-grained, i.e. when anything in the passed value changes, update.
				// This works in legacy mode because of mutable_source being updated as a whole, but when using $state
				// together with actions and mutation, it wouldn't notice the change without a deep read.
				deep_read_state(value);

				if (inited && safe_not_equal(prev, value)) {
					prev = value;
					/** @type {Function} */ (payload.update)(value);
				}
			});

			inited = true;
		}

		if (payload?.destroy) {
			return () => /** @type {Function} */ (payload.destroy)();
		}
	});
}

function r(e){var t,f,n="";if("string"==typeof e||"number"==typeof e)n+=e;else if("object"==typeof e)if(Array.isArray(e)){var o=e.length;for(t=0;t<o;t++)e[t]&&(f=r(e[t]))&&(n&&(n+=" "),n+=f);}else for(f in e)e[f]&&(n&&(n+=" "),n+=f);return n}function clsx$1(){for(var e,t,f=0,n="",o=arguments.length;f<o;f++)(e=arguments[f])&&(t=r(e))&&(n&&(n+=" "),n+=t);return n}

/**
 * Small wrapper around clsx to preserve Svelte's (weird) handling of falsy values.
 * TODO Svelte 6 revisit this, and likely turn all falsy values into the empty string (what clsx also does)
 * @param  {any} value
 */
function clsx(value) {
	if (typeof value === 'object') {
		return clsx$1(value);
	} else {
		return value ?? '';
	}
}

const whitespace = [...' \t\n\r\f\u00a0\u000b\ufeff'];

/**
 * @param {any} value
 * @param {string | null} [hash]
 * @param {Record<string, boolean>} [directives]
 * @returns {string | null}
 */
function to_class(value, hash, directives) {
	var classname = value == null ? '' : '' + value;

	if (hash) {
		classname = classname ? classname + ' ' + hash : hash;
	}

	if (directives) {
		for (var key of Object.keys(directives)) {
			if (directives[key]) {
				classname = classname ? classname + ' ' + key : key;
			} else if (classname.length) {
				var len = key.length;
				var a = 0;

				while ((a = classname.indexOf(key, a)) >= 0) {
					var b = a + len;

					if (
						(a === 0 || whitespace.includes(classname[a - 1])) &&
						(b === classname.length || whitespace.includes(classname[b]))
					) {
						classname = (a === 0 ? '' : classname.substring(0, a)) + classname.substring(b + 1);
					} else {
						a = b;
					}
				}
			}
		}
	}

	return classname === '' ? null : classname;
}

/**
 *
 * @param {Record<string,any>} styles
 * @param {boolean} important
 */
function append_styles(styles, important = false) {
	var separator = important ? ' !important;' : ';';
	var css = '';

	for (var key of Object.keys(styles)) {
		var value = styles[key];
		if (value != null && value !== '') {
			css += ' ' + key + ': ' + value + separator;
		}
	}

	return css;
}

/**
 * @param {string} name
 * @returns {string}
 */
function to_css_name(name) {
	if (name[0] !== '-' || name[1] !== '-') {
		return name.toLowerCase();
	}
	return name;
}

/**
 * @param {any} value
 * @param {Record<string, any> | [Record<string, any>, Record<string, any>]} [styles]
 * @returns {string | null}
 */
function to_style(value, styles) {
	if (styles) {
		var new_style = '';

		/** @type {Record<string,any> | undefined} */
		var normal_styles;

		/** @type {Record<string,any> | undefined} */
		var important_styles;

		if (Array.isArray(styles)) {
			normal_styles = styles[0];
			important_styles = styles[1];
		} else {
			normal_styles = styles;
		}

		if (value) {
			value = String(value)
				.replaceAll(/\s*\/\*.*?\*\/\s*/g, '')
				.trim();

			/** @type {boolean | '"' | "'"} */
			var in_str = false;
			var in_apo = 0;
			var in_comment = false;

			var reserved_names = [];

			if (normal_styles) {
				reserved_names.push(...Object.keys(normal_styles).map(to_css_name));
			}
			if (important_styles) {
				reserved_names.push(...Object.keys(important_styles).map(to_css_name));
			}

			var start_index = 0;
			var name_index = -1;

			const len = value.length;
			for (var i = 0; i < len; i++) {
				var c = value[i];

				if (in_comment) {
					if (c === '/' && value[i - 1] === '*') {
						in_comment = false;
					}
				} else if (in_str) {
					if (in_str === c) {
						in_str = false;
					}
				} else if (c === '/' && value[i + 1] === '*') {
					in_comment = true;
				} else if (c === '"' || c === "'") {
					in_str = c;
				} else if (c === '(') {
					in_apo++;
				} else if (c === ')') {
					in_apo--;
				}

				if (!in_comment && in_str === false && in_apo === 0) {
					if (c === ':' && name_index === -1) {
						name_index = i;
					} else if (c === ';' || i === len - 1) {
						if (name_index !== -1) {
							var name = to_css_name(value.substring(start_index, name_index).trim());

							if (!reserved_names.includes(name)) {
								if (c !== ';') {
									i++;
								}

								var property = value.substring(start_index, i).trim();
								new_style += ' ' + property + ';';
							}
						}

						start_index = i + 1;
						name_index = -1;
					}
				}
			}
		}

		if (normal_styles) {
			new_style += append_styles(normal_styles);
		}

		if (important_styles) {
			new_style += append_styles(important_styles, true);
		}

		new_style = new_style.trim();
		return new_style === '' ? null : new_style;
	}

	return value == null ? null : String(value);
}

/**
 * @param {Element} dom
 * @param {boolean | number} is_html
 * @param {string | null} value
 * @param {string} [hash]
 * @param {Record<string, any>} [prev_classes]
 * @param {Record<string, any>} [next_classes]
 * @returns {Record<string, boolean> | undefined}
 */
function set_class(dom, is_html, value, hash, prev_classes, next_classes) {
	// @ts-expect-error need to add __className to patched prototype
	var prev = dom.__className;

	if (
		prev !== value ||
		prev === undefined // for edge case of `class={undefined}`
	) {
		var next_class_name = to_class(value, hash, next_classes);

		{
			// Removing the attribute when the value is only an empty string causes
			// performance issues vs simply making the className an empty string. So
			// we should only remove the class if the value is nullish
			// and there no hash/directives :
			if (next_class_name == null) {
				dom.removeAttribute('class');
			} else if (is_html) {
				dom.className = next_class_name;
			} else {
				dom.setAttribute('class', next_class_name);
			}
		}

		// @ts-expect-error need to add __className to patched prototype
		dom.__className = value;
	} else if (next_classes && prev_classes !== next_classes) {
		for (var key in next_classes) {
			var is_present = !!next_classes[key];

			if (prev_classes == null || is_present !== !!prev_classes[key]) {
				dom.classList.toggle(key, is_present);
			}
		}
	}

	return next_classes;
}

/**
 * @param {Element & ElementCSSInlineStyle} dom
 * @param {Record<string, any>} prev
 * @param {Record<string, any>} next
 * @param {string} [priority]
 */
function update_styles(dom, prev = {}, next, priority) {
	for (var key in next) {
		var value = next[key];

		if (prev[key] !== value) {
			if (next[key] == null) {
				dom.style.removeProperty(key);
			} else {
				dom.style.setProperty(key, value, priority);
			}
		}
	}
}

/**
 * @param {Element & ElementCSSInlineStyle} dom
 * @param {string | null} value
 * @param {Record<string, any> | [Record<string, any>, Record<string, any>]} [prev_styles]
 * @param {Record<string, any> | [Record<string, any>, Record<string, any>]} [next_styles]
 */
function set_style(dom, value, prev_styles, next_styles) {
	// @ts-expect-error
	var prev = dom.__style;

	if (prev !== value) {
		var next_style_attr = to_style(value, next_styles);

		{
			if (next_style_attr == null) {
				dom.removeAttribute('style');
			} else {
				dom.style.cssText = next_style_attr;
			}
		}

		// @ts-expect-error
		dom.__style = value;
	} else if (next_styles) {
		if (Array.isArray(next_styles)) {
			update_styles(dom, prev_styles?.[0], next_styles[0]);
			update_styles(dom, prev_styles?.[1], next_styles[1], 'important');
		} else {
			update_styles(dom, prev_styles, next_styles);
		}
	}

	return next_styles;
}

/**
 * Selects the correct option(s) (depending on whether this is a multiple select)
 * @template V
 * @param {HTMLSelectElement} select
 * @param {V} value
 * @param {boolean} mounting
 */
function select_option(select, value, mounting = false) {
	if (select.multiple) {
		// If value is null or undefined, keep the selection as is
		if (value == undefined) {
			return;
		}

		// If not an array, warn and keep the selection as is
		if (!is_array(value)) {
			return select_multiple_invalid_value();
		}

		// Otherwise, update the selection
		for (var option of select.options) {
			option.selected = value.includes(get_option_value(option));
		}

		return;
	}

	for (option of select.options) {
		var option_value = get_option_value(option);
		if (is(option_value, value)) {
			option.selected = true;
			return;
		}
	}

	if (!mounting || value !== undefined) {
		select.selectedIndex = -1; // no option should be selected
	}
}

/**
 * Selects the correct option(s) if `value` is given,
 * and then sets up a mutation observer to sync the
 * current selection to the dom when it changes. Such
 * changes could for example occur when options are
 * inside an `#each` block.
 * @param {HTMLSelectElement} select
 */
function init_select(select) {
	var observer = new MutationObserver(() => {
		// @ts-ignore
		select_option(select, select.__value);
		// Deliberately don't update the potential binding value,
		// the model should be preserved unless explicitly changed
	});

	observer.observe(select, {
		// Listen to option element changes
		childList: true,
		subtree: true, // because of <optgroup>
		// Listen to option element value attribute changes
		// (doesn't get notified of select value changes,
		// because that property is not reflected as an attribute)
		attributes: true,
		attributeFilter: ['value']
	});

	teardown(() => {
		observer.disconnect();
	});
}

/**
 * @param {HTMLSelectElement} select
 * @param {() => unknown} get
 * @param {(value: unknown) => void} set
 * @returns {void}
 */
function bind_select_value(select, get, set = get) {
	var batches = new WeakSet();
	var mounting = true;

	listen_to_event_and_reset_event(select, 'change', (is_reset) => {
		var query = is_reset ? '[selected]' : ':checked';
		/** @type {unknown} */
		var value;

		if (select.multiple) {
			value = [].map.call(select.querySelectorAll(query), get_option_value);
		} else {
			/** @type {HTMLOptionElement | null} */
			var selected_option =
				select.querySelector(query) ??
				// will fall back to first non-disabled option if no option is selected
				select.querySelector('option:not([disabled])');
			value = selected_option && get_option_value(selected_option);
		}

		set(value);

		// @ts-ignore
		select.__value = value;

		if (current_batch !== null) {
			batches.add(current_batch);
		}
	});

	// Needs to be an effect, not a render_effect, so that in case of each loops the logic runs after the each block has updated
	effect(() => {
		var value = get();

		if (select === document.activeElement) {
			// In sync mode render effects are executed during tree traversal -> needs current_batch
			// In async mode render effects are flushed once batch resolved, at which point current_batch is null -> needs previous_batch
			var batch = /** @type {Batch} */ (current_batch);

			// Don't update the <select> if it is focused. We can get here if, for example,
			// an update is deferred because of async work depending on the select:
			//
			// <select bind:value={selected}>...</select>
			// <p>{await find(selected)}</p>
			if (batches.has(batch)) {
				return;
			}
		}

		select_option(select, value, mounting);

		// Mounting and value undefined -> take selection from dom
		if (mounting && value === undefined) {
			/** @type {HTMLOptionElement | null} */
			var selected_option = select.querySelector(':checked');
			if (selected_option !== null) {
				value = get_option_value(selected_option);
				set(value);
			}
		}

		// @ts-ignore
		select.__value = value;
		mounting = false;
	});

	init_select(select);
}

/** @param {HTMLOptionElement} option */
function get_option_value(option) {
	// __value only exists if the <option> has a value attribute
	if ('__value' in option) {
		return option.__value;
	} else {
		return option.value;
	}
}

/** @import { Blocker, Effect } from '#client' */

const IS_CUSTOM_ELEMENT = Symbol('is custom element');
const IS_HTML = Symbol('is html');

/**
 * The value/checked attribute in the template actually corresponds to the defaultValue property, so we need
 * to remove it upon hydration to avoid a bug when someone resets the form value.
 * @param {HTMLInputElement} input
 * @returns {void}
 */
function remove_input_defaults(input) {
	return;
}

/**
 * Sets the `selected` attribute on an `option` element.
 * Not set through the property because that doesn't reflect to the DOM,
 * which means it wouldn't be taken into account when a form is reset.
 * @param {HTMLOptionElement} element
 * @param {boolean} selected
 */
function set_selected(element, selected) {
	if (selected) {
		// The selected option could've changed via user selection, and
		// setting the value without this check would set it back.
		if (!element.hasAttribute('selected')) {
			element.setAttribute('selected', '');
		}
	} else {
		element.removeAttribute('selected');
	}
}

/**
 * @param {Element} element
 * @param {string} attribute
 * @param {string | null} value
 * @param {boolean} [skip_warning]
 */
function set_attribute(element, attribute, value, skip_warning) {
	var attributes = get_attributes(element);

	if (attributes[attribute] === (attributes[attribute] = value)) return;

	if (attribute === 'loading') {
		// @ts-expect-error
		element[LOADING_ATTR_SYMBOL] = value;
	}

	if (value == null) {
		element.removeAttribute(attribute);
	} else if (typeof value !== 'string' && get_setters(element).includes(attribute)) {
		// @ts-ignore
		element[attribute] = value;
	} else {
		element.setAttribute(attribute, value);
	}
}

/**
 *
 * @param {Element} element
 */
function get_attributes(element) {
	return /** @type {Record<string | symbol, unknown>} **/ (
		// @ts-expect-error
		element.__attributes ??= {
			[IS_CUSTOM_ELEMENT]: element.nodeName.includes('-'),
			[IS_HTML]: element.namespaceURI === NAMESPACE_HTML
		}
	);
}

/** @type {Map<string, string[]>} */
var setters_cache = new Map();

/** @param {Element} element */
function get_setters(element) {
	var cache_key = element.getAttribute('is') || element.nodeName;
	var setters = setters_cache.get(cache_key);
	if (setters) return setters;
	setters_cache.set(cache_key, (setters = []));

	var descriptors;
	var proto = element; // In the case of custom elements there might be setters on the instance
	var element_proto = Element.prototype;

	// Stop at Element, from there on there's only unnecessary setters we're not interested in
	// Do not use contructor.name here as that's unreliable in some browser environments
	while (element_proto !== proto) {
		descriptors = get_descriptors(proto);

		for (var key in descriptors) {
			if (descriptors[key].set) {
				setters.push(key);
			}
		}

		proto = get_prototype_of(proto);
	}

	return setters;
}

/** @import { Batch } from '../../../reactivity/batch.js' */

/**
 * @param {HTMLInputElement} input
 * @param {() => unknown} get
 * @param {(value: unknown) => void} set
 * @returns {void}
 */
function bind_value(input, get, set = get) {
	var batches = new WeakSet();

	listen_to_event_and_reset_event(input, 'input', async (is_reset) => {
		if (DEV && input.type === 'checkbox') {
			// TODO should this happen in prod too?
			bind_invalid_checkbox_value();
		}

		/** @type {any} */
		var value = is_reset ? input.defaultValue : input.value;
		value = is_numberlike_input(input) ? to_number(value) : value;
		set(value);

		if (current_batch !== null) {
			batches.add(current_batch);
		}

		// Because `{#each ...}` blocks work by updating sources inside the flush,
		// we need to wait a tick before checking to see if we should forcibly
		// update the input and reset the selection state
		await tick();

		// Respect any validation in accessors
		if (value !== (value = get())) {
			var start = input.selectionStart;
			var end = input.selectionEnd;
			var length = input.value.length;

			// the value is coerced on assignment
			input.value = value ?? '';

			// Restore selection
			if (end !== null) {
				var new_length = input.value.length;
				// If cursor was at end and new input is longer, move cursor to new end
				if (start === end && end === length && new_length > length) {
					input.selectionStart = new_length;
					input.selectionEnd = new_length;
				} else {
					input.selectionStart = start;
					input.selectionEnd = Math.min(end, new_length);
				}
			}
		}
	});

	if (
		// If we are hydrating and the value has since changed,
		// then use the updated value from the input instead.
		// If defaultValue is set, then value == defaultValue
		// TODO Svelte 6: remove input.value check and set to empty string?
		(untrack(get) == null && input.value)
	) {
		set(is_numberlike_input(input) ? to_number(input.value) : input.value);

		if (current_batch !== null) {
			batches.add(current_batch);
		}
	}

	render_effect(() => {
		if (DEV && input.type === 'checkbox') {
			// TODO should this happen in prod too?
			bind_invalid_checkbox_value();
		}

		var value = get();

		if (input === document.activeElement) {
			// In sync mode render effects are executed during tree traversal -> needs current_batch
			// In async mode render effects are flushed once batch resolved, at which point current_batch is null -> needs previous_batch
			var batch = /** @type {Batch} */ (current_batch);

			// Never rewrite the contents of a focused input. We can get here if, for example,
			// an update is deferred because of async work depending on the input:
			//
			// <input bind:value={query}>
			// <p>{await find(query)}</p>
			if (batches.has(batch)) {
				return;
			}
		}

		if (is_numberlike_input(input) && value === to_number(input.value)) {
			// handles 0 vs 00 case (see https://github.com/sveltejs/svelte/issues/9959)
			return;
		}

		if (input.type === 'date' && !value && !input.value) {
			// Handles the case where a temporarily invalid date is set (while typing, for example with a leading 0 for the day)
			// and prevents this state from clearing the other parts of the date input (see https://github.com/sveltejs/svelte/issues/7897)
			return;
		}

		// don't set the value of the input if it's the same to allow
		// minlength to work properly
		if (value !== input.value) {
			// @ts-expect-error the value is coerced on assignment
			input.value = value ?? '';
		}
	});
}

/** @type {Set<HTMLInputElement[]>} */
const pending = new Set();

/**
 * @param {HTMLInputElement[]} inputs
 * @param {null | [number]} group_index
 * @param {HTMLInputElement} input
 * @param {() => unknown} get
 * @param {(value: unknown) => void} set
 * @returns {void}
 */
function bind_group(inputs, group_index, input, get, set = get) {
	var is_checkbox = input.getAttribute('type') === 'checkbox';
	var binding_group = inputs;

	if (group_index !== null) {
		for (var index of group_index) {
			// @ts-expect-error
			binding_group = binding_group[index] ??= [];
		}
	}

	binding_group.push(input);

	listen_to_event_and_reset_event(
		input,
		'change',
		() => {
			// @ts-ignore
			var value = input.__value;

			if (is_checkbox) {
				value = get_binding_group_value(binding_group, value, input.checked);
			}

			set(value);
		},
		// TODO better default value handling
		() => set(is_checkbox ? [] : null)
	);

	render_effect(() => {
		var value = get();

		if (is_checkbox) {
			value = value || [];
			// @ts-ignore
			input.checked = value.includes(input.__value);
		} else {
			// @ts-ignore
			input.checked = is(input.__value, value);
		}
	});

	teardown(() => {
		var index = binding_group.indexOf(input);

		if (index !== -1) {
			binding_group.splice(index, 1);
		}
	});

	if (!pending.has(binding_group)) {
		pending.add(binding_group);

		queue_micro_task(() => {
			// necessary to maintain binding group order in all insertion scenarios
			binding_group.sort((a, b) => (a.compareDocumentPosition(b) === 4 ? -1 : 1));
			pending.delete(binding_group);
		});
	}

	queue_micro_task(() => {
	});
}

/**
 * @param {HTMLInputElement} input
 * @param {() => unknown} get
 * @param {(value: unknown) => void} set
 * @returns {void}
 */
function bind_checked(input, get, set = get) {
	listen_to_event_and_reset_event(input, 'change', (is_reset) => {
		var value = is_reset ? input.defaultChecked : input.checked;
		set(value);
	});

	if (
		// If we are hydrating and the value has since changed,
		// then use the update value from the input instead.
		// If defaultChecked is set, then checked == defaultChecked
		untrack(get) == null
	) {
		set(input.checked);
	}

	render_effect(() => {
		var value = get();
		input.checked = Boolean(value);
	});
}

/**
 * @template V
 * @param {Array<HTMLInputElement>} group
 * @param {V} __value
 * @param {boolean} checked
 * @returns {V[]}
 */
function get_binding_group_value(group, __value, checked) {
	/** @type {Set<V>} */
	var value = new Set();

	for (var i = 0; i < group.length; i += 1) {
		if (group[i].checked) {
			// @ts-ignore
			value.add(group[i].__value);
		}
	}

	if (!checked) {
		value.delete(__value);
	}

	return Array.from(value);
}

/**
 * @param {HTMLInputElement} input
 */
function is_numberlike_input(input) {
	var type = input.type;
	return type === 'number' || type === 'range';
}

/**
 * @param {string} value
 */
function to_number(value) {
	return value === '' ? null : +value;
}

/**
 * We create one listener for all elements
 * @see {@link https://groups.google.com/a/chromium.org/g/blink-dev/c/z6ienONUb5A/m/F5-VcUZtBAAJ Explanation}
 */
class ResizeObserverSingleton {
	/** */
	#listeners = new WeakMap();

	/** @type {ResizeObserver | undefined} */
	#observer;

	/** @type {ResizeObserverOptions} */
	#options;

	/** @static */
	static entries = new WeakMap();

	/** @param {ResizeObserverOptions} options */
	constructor(options) {
		this.#options = options;
	}

	/**
	 * @param {Element} element
	 * @param {(entry: ResizeObserverEntry) => any} listener
	 */
	observe(element, listener) {
		var listeners = this.#listeners.get(element) || new Set();
		listeners.add(listener);

		this.#listeners.set(element, listeners);
		this.#getObserver().observe(element, this.#options);

		return () => {
			var listeners = this.#listeners.get(element);
			listeners.delete(listener);

			if (listeners.size === 0) {
				this.#listeners.delete(element);
				/** @type {ResizeObserver} */ (this.#observer).unobserve(element);
			}
		};
	}

	#getObserver() {
		return (
			this.#observer ??
			(this.#observer = new ResizeObserver(
				/** @param {any} entries */ (entries) => {
					for (var entry of entries) {
						ResizeObserverSingleton.entries.set(entry.target, entry);
						for (var listener of this.#listeners.get(entry.target) || []) {
							listener(entry);
						}
					}
				}
			))
		);
	}
}

var resize_observer_border_box = /* @__PURE__ */ new ResizeObserverSingleton({
	box: 'border-box'
});

/**
 * @param {HTMLElement} element
 * @param {'clientWidth' | 'clientHeight' | 'offsetWidth' | 'offsetHeight'} type
 * @param {(size: number) => void} set
 */
function bind_element_size(element, type, set) {
	var unsub = resize_observer_border_box.observe(element, () => set(element[type]));

	effect(() => {
		// The update could contain reads which should be ignored
		untrack(() => set(element[type]));
		return unsub;
	});
}

/** @import { ComponentContext, Effect } from '#client' */

/**
 * @param {any} bound_value
 * @param {Element} element_or_component
 * @returns {boolean}
 */
function is_bound_this(bound_value, element_or_component) {
	return (
		bound_value === element_or_component || bound_value?.[STATE_SYMBOL] === element_or_component
	);
}

/**
 * @param {any} element_or_component
 * @param {(value: unknown, ...parts: unknown[]) => void} update
 * @param {(...parts: unknown[]) => unknown} get_value
 * @param {() => unknown[]} [get_parts] Set if the this binding is used inside an each block,
 * 										returns all the parts of the each block context that are used in the expression
 * @returns {void}
 */
function bind_this(element_or_component = {}, update, get_value, get_parts) {
	var component_effect = /** @type {ComponentContext} */ (component_context).r;
	var parent = /** @type {Effect} */ (active_effect);

	effect(() => {
		/** @type {unknown[]} */
		var old_parts;

		/** @type {unknown[]} */
		var parts;

		render_effect(() => {
			old_parts = parts;
			// We only track changes to the parts, not the value itself to avoid unnecessary reruns.
			parts = get_parts?.() || [];

			untrack(() => {
				if (element_or_component !== get_value(...parts)) {
					update(element_or_component, ...parts);
					// If this is an effect rerun (cause: each block context changes), then nullify the binding at
					// the previous position if it isn't already taken over by a different effect.
					if (old_parts && is_bound_this(get_value(...old_parts), element_or_component)) {
						update(null, ...old_parts);
					}
				}
			});
		});

		return () => {
			// When the bind:this effect is destroyed, we go up the effect parent chain until we find the last parent effect that is destroyed,
			// or the effect containing the component bind:this is in (whichever comes first). That way we can time the nulling of the binding
			// as close to user/developer expectation as possible.
			// TODO Svelte 6: Decide if we want to keep this logic or just always null the binding in the component effect's teardown
			// (which would be simpler, but less intuitive in some cases, and breaks the `ondestroy-before-cleanup` test)
			let p = parent;
			while (p !== component_effect && p.parent !== null && p.parent.f & DESTROYING) {
				p = p.parent;
			}
			const teardown = () => {
				if (parts && is_bound_this(get_value(...parts), element_or_component)) {
					update(null, ...parts);
				}
			};
			const original_teardown = p.teardown;
			p.teardown = () => {
				teardown();
				original_teardown?.();
			};
		};
	});

	return element_or_component;
}

/** @import { ComponentContextLegacy } from '#client' */

/**
 * Legacy-mode only: Call `onMount` callbacks and set up `beforeUpdate`/`afterUpdate` effects
 * @param {boolean} [immutable]
 */
function init(immutable = false) {
	const context = /** @type {ComponentContextLegacy} */ (component_context);

	const callbacks = context.l.u;
	if (!callbacks) return;

	let props = () => deep_read_state(context.s);

	if (immutable) {
		let version = 0;
		let prev = /** @type {Record<string, any>} */ ({});

		// In legacy immutable mode, before/afterUpdate only fire if the object identity of a prop changes
		const d = derived(() => {
			let changed = false;
			const props = context.s;
			for (const key in props) {
				if (props[key] !== prev[key]) {
					prev[key] = props[key];
					changed = true;
				}
			}
			if (changed) version++;
			return version;
		});

		props = () => get(d);
	}

	// beforeUpdate
	if (callbacks.b.length) {
		user_pre_effect(() => {
			observe_all(context, props);
			run_all(callbacks.b);
		});
	}

	// onMount (must run before afterUpdate)
	user_effect(() => {
		const fns = untrack(() => callbacks.m.map(run));
		return () => {
			for (const fn of fns) {
				if (typeof fn === 'function') {
					fn();
				}
			}
		};
	});

	// afterUpdate
	if (callbacks.a.length) {
		user_effect(() => {
			observe_all(context, props);
			run_all(callbacks.a);
		});
	}
}

/**
 * Invoke the getter of all signals associated with a component
 * so they can be registered to the effect this function is called in.
 * @param {ComponentContextLegacy} context
 * @param {(() => void)} props
 */
function observe_all(context, props) {
	if (context.l.s) {
		for (const signal of context.l.s) get(signal);
	}

	props();
}

/**
 * @this {any}
 * @param {Record<string, unknown>} $$props
 * @param {Event} event
 * @returns {void}
 */
function bubble_event($$props, event) {
	var events = /** @type {Record<string, Function[] | Function>} */ ($$props.$$events)?.[
		event.type
	];

	var callbacks = is_array(events) ? events.slice() : events == null ? [] : [events];

	for (var fn of callbacks) {
		// Preserve "this" context
		fn.call(this, event);
	}
}

/** @import { Effect, Source } from './types.js' */

/**
 * The proxy handler for spread props. Handles the incoming array of props
 * that looks like `() => { dynamic: props }, { static: prop }, ..` and wraps
 * them so that the whole thing is passed to the component as the `$$props` argument.
 * @type {ProxyHandler<{ props: Array<Record<string | symbol, unknown> | (() => Record<string | symbol, unknown>)> }>}}
 */
const spread_props_handler = {
	get(target, key) {
		let i = target.props.length;
		while (i--) {
			let p = target.props[i];
			if (is_function(p)) p = p();
			if (typeof p === 'object' && p !== null && key in p) return p[key];
		}
	},
	set(target, key, value) {
		let i = target.props.length;
		while (i--) {
			let p = target.props[i];
			if (is_function(p)) p = p();
			const desc = get_descriptor(p, key);
			if (desc && desc.set) {
				desc.set(value);
				return true;
			}
		}
		return false;
	},
	getOwnPropertyDescriptor(target, key) {
		let i = target.props.length;
		while (i--) {
			let p = target.props[i];
			if (is_function(p)) p = p();
			if (typeof p === 'object' && p !== null && key in p) {
				const descriptor = get_descriptor(p, key);
				if (descriptor && !descriptor.configurable) {
					// Prevent a "Non-configurability Report Error": The target is an array, it does
					// not actually contain this property. If it is now described as non-configurable,
					// the proxy throws a validation error. Setting it to true avoids that.
					descriptor.configurable = true;
				}
				return descriptor;
			}
		}
	},
	has(target, key) {
		// To prevent a false positive `is_entry_props` in the `prop` function
		if (key === STATE_SYMBOL || key === LEGACY_PROPS) return false;

		for (let p of target.props) {
			if (is_function(p)) p = p();
			if (p != null && key in p) return true;
		}

		return false;
	},
	ownKeys(target) {
		/** @type {Array<string | symbol>} */
		const keys = [];

		for (let p of target.props) {
			if (is_function(p)) p = p();
			if (!p) continue;

			for (const key in p) {
				if (!keys.includes(key)) keys.push(key);
			}

			for (const key of Object.getOwnPropertySymbols(p)) {
				if (!keys.includes(key)) keys.push(key);
			}
		}

		return keys;
	}
};

/**
 * @param {Array<Record<string, unknown> | (() => Record<string, unknown>)>} props
 * @returns {any}
 */
function spread_props(...props) {
	return new Proxy({ props }, spread_props_handler);
}

/**
 * This function is responsible for synchronizing a possibly bound prop with the inner component state.
 * It is used whenever the compiler sees that the component writes to the prop, or when it has a default prop_value.
 * @template V
 * @param {Record<string, unknown>} props
 * @param {string} key
 * @param {number} flags
 * @param {V | (() => V)} [fallback]
 * @returns {(() => V | ((arg: V) => V) | ((arg: V, mutation: boolean) => V))}
 */
function prop(props, key, flags, fallback) {
	var runes = !legacy_mode_flag || (flags & PROPS_IS_RUNES) !== 0;
	var bindable = (flags & PROPS_IS_BINDABLE) !== 0;
	var lazy = (flags & PROPS_IS_LAZY_INITIAL) !== 0;

	var fallback_value = /** @type {V} */ (fallback);
	var fallback_dirty = true;

	var get_fallback = () => {
		if (fallback_dirty) {
			fallback_dirty = false;

			fallback_value = lazy
				? untrack(/** @type {() => V} */ (fallback))
				: /** @type {V} */ (fallback);
		}

		return fallback_value;
	};

	/** @type {((v: V) => void) | undefined} */
	let setter;

	if (bindable) {
		// Can be the case when someone does `mount(Component, props)` with `let props = $state({...})`
		// or `createClassComponent(Component, props)`
		var is_entry_props = STATE_SYMBOL in props || LEGACY_PROPS in props;

		setter =
			get_descriptor(props, key)?.set ??
			(is_entry_props && key in props ? (v) => (props[key] = v) : undefined);
	}

	/** @type {V} */
	var initial_value;
	var is_store_sub = false;

	if (bindable) {
		[initial_value, is_store_sub] = capture_store_binding(() => /** @type {V} */ (props[key]));
	} else {
		initial_value = /** @type {V} */ (props[key]);
	}

	if (initial_value === undefined && fallback !== undefined) {
		initial_value = get_fallback();

		if (setter) {
			if (runes) props_invalid_value(key);
			setter(initial_value);
		}
	}

	/** @type {() => V} */
	var getter;

	if (runes) {
		getter = () => {
			var value = /** @type {V} */ (props[key]);
			if (value === undefined) return get_fallback();
			fallback_dirty = true;
			return value;
		};
	} else {
		getter = () => {
			var value = /** @type {V} */ (props[key]);

			if (value !== undefined) {
				// in legacy mode, we don't revert to the fallback value
				// if the prop goes from defined to undefined. The easiest
				// way to model this is to make the fallback undefined
				// as soon as the prop has a value
				fallback_value = /** @type {V} */ (undefined);
			}

			return value === undefined ? fallback_value : value;
		};
	}

	// prop is never written to — we only need a getter
	if (runes && (flags & PROPS_IS_UPDATED) === 0) {
		return getter;
	}

	// prop is written to, but the parent component had `bind:foo` which
	// means we can just call `$$props.foo = value` directly
	if (setter) {
		var legacy_parent = props.$$legacy;
		return /** @type {() => V} */ (
			function (/** @type {V} */ value, /** @type {boolean} */ mutation) {
				if (arguments.length > 0) {
					// We don't want to notify if the value was mutated and the parent is in runes mode.
					// In that case the state proxy (if it exists) should take care of the notification.
					// If the parent is not in runes mode, we need to notify on mutation, too, that the prop
					// has changed because the parent will not be able to detect the change otherwise.
					if (!runes || !mutation || legacy_parent || is_store_sub) {
						/** @type {Function} */ (setter)(mutation ? getter() : value);
					}

					return value;
				}

				return getter();
			}
		);
	}

	// Either prop is written to, but there's no binding, which means we
	// create a derived that we can write to locally.
	// Or we are in legacy mode where we always create a derived to replicate that
	// Svelte 4 did not trigger updates when a primitive value was updated to the same value.
	var overridden = false;

	var d = ((flags & PROPS_IS_IMMUTABLE) !== 0 ? derived : derived_safe_equal)(() => {
		overridden = false;
		return getter();
	});

	if (DEV) {
		d.label = key;
	}

	// Capture the initial value if it's bindable
	if (bindable) get(d);

	var parent_effect = /** @type {Effect} */ (active_effect);

	return /** @type {() => V} */ (
		function (/** @type {any} */ value, /** @type {boolean} */ mutation) {
			if (arguments.length > 0) {
				const new_value = mutation ? get(d) : runes && bindable ? proxy(value) : value;

				set(d, new_value);
				overridden = true;

				if (fallback_value !== undefined) {
					fallback_value = new_value;
				}

				return value;
			}

			// special case — avoid recalculating the derived if we're in a
			// teardown function and the prop was overridden locally, or the
			// component was already destroyed (people could access props in a timeout)
			if ((is_destroying_effect && overridden) || (parent_effect.f & DESTROYED) !== 0) {
				return d.v;
			}

			return get(d);
		}
	);
}

/** @import { ComponentContext, ComponentContextLegacy } from '#client' */
/** @import { EventDispatcher } from './index.js' */
/** @import { NotFunction } from './internal/types.js' */

if (DEV) {
	/**
	 * @param {string} rune
	 */
	function throw_rune_error(rune) {
		if (!(rune in globalThis)) {
			// TODO if people start adjusting the "this can contain runes" config through v-p-s more, adjust this message
			/** @type {any} */
			let value; // let's hope noone modifies this global, but belts and braces
			Object.defineProperty(globalThis, rune, {
				configurable: true,
				// eslint-disable-next-line getter-return
				get: () => {
					if (value !== undefined) {
						return value;
					}

					rune_outside_svelte(rune);
				},
				set: (v) => {
					value = v;
				}
			});
		}
	}

	throw_rune_error('$state');
	throw_rune_error('$effect');
	throw_rune_error('$derived');
	throw_rune_error('$inspect');
	throw_rune_error('$props');
	throw_rune_error('$bindable');
}

/**
 * `onMount`, like [`$effect`](https://svelte.dev/docs/svelte/$effect), schedules a function to run as soon as the component has been mounted to the DOM.
 * Unlike `$effect`, the provided function only runs once.
 *
 * It must be called during the component's initialisation (but doesn't need to live _inside_ the component;
 * it can be called from an external module). If a function is returned _synchronously_ from `onMount`,
 * it will be called when the component is unmounted.
 *
 * `onMount` functions do not run during [server-side rendering](https://svelte.dev/docs/svelte/svelte-server#render).
 *
 * @template T
 * @param {() => NotFunction<T> | Promise<NotFunction<T>> | (() => any)} fn
 * @returns {void}
 */
function onMount(fn) {
	if (component_context === null) {
		lifecycle_outside_component('onMount');
	}

	if (legacy_mode_flag && component_context.l !== null) {
		init_update_callbacks(component_context).m.push(fn);
	} else {
		user_effect(() => {
			const cleanup = untrack(fn);
			if (typeof cleanup === 'function') return /** @type {() => void} */ (cleanup);
		});
	}
}

/**
 * Schedules a callback to run immediately before the component is unmounted.
 *
 * Out of `onMount`, `beforeUpdate`, `afterUpdate` and `onDestroy`, this is the
 * only one that runs inside a server-side component.
 *
 * @param {() => any} fn
 * @returns {void}
 */
function onDestroy(fn) {
	if (component_context === null) {
		lifecycle_outside_component('onDestroy');
	}

	onMount(() => () => untrack(fn));
}

/**
 * @template [T=any]
 * @param {string} type
 * @param {T} [detail]
 * @param {any}params_0
 * @returns {CustomEvent<T>}
 */
function create_custom_event(type, detail, { bubbles = false, cancelable = false } = {}) {
	return new CustomEvent(type, { detail, bubbles, cancelable });
}

/**
 * Creates an event dispatcher that can be used to dispatch [component events](https://svelte.dev/docs/svelte/legacy-on#Component-events).
 * Event dispatchers are functions that can take two arguments: `name` and `detail`.
 *
 * Component events created with `createEventDispatcher` create a
 * [CustomEvent](https://developer.mozilla.org/en-US/docs/Web/API/CustomEvent).
 * These events do not [bubble](https://developer.mozilla.org/en-US/docs/Learn/JavaScript/Building_blocks/Events#Event_bubbling_and_capture).
 * The `detail` argument corresponds to the [CustomEvent.detail](https://developer.mozilla.org/en-US/docs/Web/API/CustomEvent/detail)
 * property and can contain any type of data.
 *
 * The event dispatcher can be typed to narrow the allowed event names and the type of the `detail` argument:
 * ```ts
 * const dispatch = createEventDispatcher<{
 *  loaded: null; // does not take a detail argument
 *  change: string; // takes a detail argument of type string, which is required
 *  optional: number | null; // takes an optional detail argument of type number
 * }>();
 * ```
 *
 * @deprecated Use callback props and/or the `$host()` rune instead — see [migration guide](https://svelte.dev/docs/svelte/v5-migration-guide#Event-changes-Component-events)
 * @template {Record<string, any>} [EventMap = any]
 * @returns {EventDispatcher<EventMap>}
 */
function createEventDispatcher() {
	const active_component_context = component_context;
	if (active_component_context === null) {
		lifecycle_outside_component('createEventDispatcher');
	}

	/**
	 * @param [detail]
	 * @param [options]
	 */
	return (type, detail, options) => {
		const events = /** @type {Record<string, Function | Function[]>} */ (
			active_component_context.s.$$events
		)?.[/** @type {string} */ (type)];

		if (events) {
			const callbacks = is_array(events) ? events.slice() : [events];
			// TODO are there situations where events could be dispatched
			// in a server (non-DOM) environment?
			const event = create_custom_event(/** @type {string} */ (type), detail, options);
			for (const fn of callbacks) {
				fn.call(active_component_context.x, event);
			}
			return !event.defaultPrevented;
		}

		return true;
	};
}

/**
 * Schedules a callback to run immediately after the component has been updated.
 *
 * The first time the callback runs will be after the initial `onMount`.
 *
 * In runes mode use `$effect` instead.
 *
 * @deprecated Use [`$effect`](https://svelte.dev/docs/svelte/$effect) instead
 * @param {() => void} fn
 * @returns {void}
 */
function afterUpdate(fn) {
	if (component_context === null) {
		lifecycle_outside_component('afterUpdate');
	}

	if (component_context.l === null) {
		lifecycle_legacy_only('afterUpdate');
	}

	init_update_callbacks(component_context).a.push(fn);
}

/**
 * Legacy-mode: Init callbacks object for onMount/beforeUpdate/afterUpdate
 * @param {ComponentContext} context
 */
function init_update_callbacks(context) {
	var l = /** @type {ComponentContextLegacy} */ (context).l;
	return (l.u ??= { a: [], b: [], m: [] });
}

// generated during release, do not modify

const PUBLIC_VERSION = '5';

if (typeof window !== 'undefined') {
	// @ts-expect-error
	((window.__svelte ??= {}).v ??= new Set()).add(PUBLIC_VERSION);
}

/**
 * Does the current object have different keys or values compared to the previous version?
 *
 * @param {object} previous
 * @param {object} current
 *
 * @returns {boolean}
 */
function objectsDiffer( [previous, current] ) {
	if ( !previous || !current ) {
		return false;
	}

	// Any difference in keys?
	const prevKeys = Object.keys( previous );
	const currKeys = Object.keys( current );

	if ( prevKeys.length !== currKeys.length ) {
		return true;
	}

	// Symmetrical diff to find extra keys in either object.
	if (
		prevKeys.filter( x => !currKeys.includes( x ) )
			.concat(
				currKeys.filter( x => !prevKeys.includes( x ) )
			)
			.length > 0
	) {
		return true;
	}

	// Any difference in values?
	for ( const key in previous ) {
		if ( JSON.stringify( current[ key ] ) !== JSON.stringify( previous[ key ] ) ) {
			return true;
		}
	}

	return false;
}

// Initial config store.
const config = writable( {} );

// Whether settings are locked due to background activity such as upgrade.
const settingsLocked = writable( false );

// Convenience readable store of server's settings, derived from config.
const current_settings = derived$1( config, $config => $config.settings );

// Convenience readable store of defined settings keys, derived from config.
const defined_settings = derived$1( config, $config => $config.defined_settings );

// Convenience readable store of translated strings, derived from config.
const strings = derived$1( config, $config => $config.strings );

// Convenience readable store for nonce, derived from config.
const nonce = derived$1( config, $config => $config.nonce );

// Convenience readable store of urls, derived from config.
const urls = derived$1( config, $config => $config.urls );

// Convenience readable store of docs, derived from config.
const docs = derived$1( config, $config => $config.docs );

// Convenience readable store of api endpoints, derived from config.
const endpoints = derived$1( config, $config => $config.endpoints );

// Convenience readable store of diagnostics, derived from config.
const diagnostics = derived$1( config, $config => $config.diagnostics );

// Convenience readable store of counts, derived from config.
const counts = derived$1( config, $config => $config.counts );

// Convenience readable store of summary counts, derived from config.
const summaryCounts = derived$1( config, $config => $config.summary_counts );

// Convenience readable store of offload remaining upsell, derived from config.
const offloadRemainingUpsell = derived$1( config, $config => $config.offload_remaining_upsell );

// Convenience readable store of upgrades, derived from config.
derived$1( config, $config => $config.upgrades );

// Convenience readable store of whether plugin is set up, derived from config.
const is_plugin_setup = derived$1( config, $config => $config.is_plugin_setup );

// Convenience readable store of whether plugin is set up, including with credentials, derived from config.
const is_plugin_setup_with_credentials = derived$1( config, $config => $config.is_plugin_setup_with_credentials );

// Convenience readable store of whether storage provider needs access credentials, derived from config.
const needs_access_keys = derived$1( config, $config => $config.needs_access_keys );

// Convenience readable store of whether bucket is writable, derived from config.
derived$1( config, $config => $config.bucket_writable );

// Convenience readable store of settings validation results, derived from config.
const settings_validation = derived$1( config, $config => $config.settings_validation );

// Store of inline errors and warnings to be shown next to settings.
// Format is a map using settings key for keys, values are an array of objects that can be used to instantiate a notification.
const settings_notifications = writable( new Map() );

// Store of validation errors for settings.
// Format is a map using settings key for keys, values are strings containing validation error.
const validationErrors = writable( new Map() );

// Whether settings validations are being run.
const revalidatingSettings = writable( false );

// Does the app need a page refresh to resolve conflicts?
const needs_refresh = writable( false );

// Various stores may call the API, and the api object uses some stores.
// To avoid cyclic dependencies, we therefore co-locate the api object with the stores.
// We also need to add its functions much later so that JSHint does not complain about using the stores too early.
const api = {};

/**
 * Creates store of settings.
 *
 * @return {Object}
 */
function createSettings() {
	const { subscribe, set, update } = writable( [] );

	return {
		subscribe,
		set,
		async save() {
			const json = await api.put( "settings", get$1( this ) );

			if ( json.hasOwnProperty( "saved" ) && true === json.saved ) {
				// Sync settings with what the server has.
				this.updateSettings( json );

				return json;
			}

			return { "saved": false };
		},
		reset() {
			set( { ...get$1( current_settings ) } );
		},
		async fetch() {
			const json = await api.get( "settings", {} );
			this.updateSettings( json );
		},
		updateSettings( json ) {
			if (
				json.hasOwnProperty( "defined_settings" ) &&
				json.hasOwnProperty( "settings" ) &&
				json.hasOwnProperty( "storage_providers" ) &&
				json.hasOwnProperty( "delivery_providers" ) &&
				json.hasOwnProperty( "is_plugin_setup" ) &&
				json.hasOwnProperty( "is_plugin_setup_with_credentials" ) &&
				json.hasOwnProperty( "needs_access_keys" ) &&
				json.hasOwnProperty( "bucket_writable" ) &&
				json.hasOwnProperty( "urls" )
			) {
				// Update our understanding of what the server's settings are.
				config.update( $config => {
					return {
						...$config,
						defined_settings: json.defined_settings,
						settings: json.settings,
						storage_providers: json.storage_providers,
						delivery_providers: json.delivery_providers,
						is_plugin_setup: json.is_plugin_setup,
						is_plugin_setup_with_credentials: json.is_plugin_setup_with_credentials,
						needs_access_keys: json.needs_access_keys,
						bucket_writable: json.bucket_writable,
						urls: json.urls
					};
				} );
				// Update our local working copy of the settings.
				update( $settings => {
					return { ...json.settings };
				} );
			}
		}
	};
}

const settings = createSettings();

// Have the settings been changed from current server side settings?
const settings_changed = derived$1( [settings, current_settings], objectsDiffer );

// Convenience readable store of default storage provider, derived from config.
const defaultStorageProvider = derived$1( config, $config => $config.default_storage_provider );

// Convenience readable store of available storage providers.
const storage_providers = derived$1( [config, urls], ( [$config, $urls] ) => {
	for ( const key in $config.storage_providers ) {
		$config.storage_providers[ key ].icon = $urls.assets + "img/icon/provider/storage/" + $config.storage_providers[ key ].provider_key_name + ".svg";
		$config.storage_providers[ key ].link_icon = $urls.assets + "img/icon/provider/storage/" + $config.storage_providers[ key ].provider_key_name + "-link.svg";
		$config.storage_providers[ key ].round_icon = $urls.assets + "img/icon/provider/storage/" + $config.storage_providers[ key ].provider_key_name + "-round.svg";
	}

	return $config.storage_providers;
} );

// Convenience readable store of storage provider's details.
const storage_provider = derived$1( [settings, storage_providers], ( [$settings, $storage_providers] ) => {
	if ( $settings.hasOwnProperty( "provider" ) && $storage_providers.hasOwnProperty( $settings.provider ) ) {
		return $storage_providers[ $settings.provider ];
	} else {
		return [];
	}
} );

// Convenience readable store of default delivery provider, derived from config.
derived$1( config, $config => $config.default_delivery_provider );

// Convenience readable store of available delivery providers.
const delivery_providers = derived$1( [config, urls, storage_provider], ( [$config, $urls, $storage_provider] ) => {
	for ( const key in $config.delivery_providers ) {
		if ( "storage" === key ) {
			$config.delivery_providers[ key ].icon = $storage_provider.icon;
			$config.delivery_providers[ key ].round_icon = $storage_provider.round_icon;
			$config.delivery_providers[ key ].provider_service_quick_start_url = $storage_provider.provider_service_quick_start_url;
		} else {
			$config.delivery_providers[ key ].icon = $urls.assets + "img/icon/provider/delivery/" + $config.delivery_providers[ key ].provider_key_name + ".svg";
			$config.delivery_providers[ key ].round_icon = $urls.assets + "img/icon/provider/delivery/" + $config.delivery_providers[ key ].provider_key_name + "-round.svg";
		}
	}

	return $config.delivery_providers;
} );

// Convenience readable store of delivery provider's details.
const delivery_provider = derived$1( [settings, delivery_providers, urls], ( [$settings, $delivery_providers, $urls] ) => {
	if ( $settings.hasOwnProperty( "delivery-provider" ) && $delivery_providers.hasOwnProperty( $settings[ "delivery-provider" ] ) ) {
		return $delivery_providers[ $settings[ "delivery-provider" ] ];
	} else {
		return [];
	}
} );

// Full name for current region.
const region_name = derived$1( [settings, storage_provider, strings], ( [$settings, $storage_provider, $strings] ) => {
	if ( $settings.region && $storage_provider.regions && $storage_provider.regions.hasOwnProperty( $settings.region ) ) {
		return $storage_provider.regions[ $settings.region ];
	} else if ( $settings.region && $storage_provider.regions ) {
		// Region set but not available in list of regions.
		return $strings.unknown;
	} else if ( $storage_provider.default_region && $storage_provider.regions && $storage_provider.regions.hasOwnProperty( $storage_provider.default_region ) ) {
		// Region not set but default available.
		return $storage_provider.regions[ $storage_provider.default_region ];
	} else {
		// Possibly no default region or regions available.
		return $strings.unknown;
	}
} );

// Convenience readable store of whether Block All Public Access is enabled.
derived$1( [settings, storage_provider], ( [$settings, $storage_provider] ) => {
	return $storage_provider.block_public_access_supported && $settings.hasOwnProperty( "block-public-access" ) && $settings[ "block-public-access" ];
} );

// Convenience readable store of whether Object Ownership is enforced.
derived$1( [settings, storage_provider], ( [$settings, $storage_provider] ) => {
	return $storage_provider.object_ownership_supported && $settings.hasOwnProperty( "object-ownership-enforced" ) && $settings[ "object-ownership-enforced" ];
} );

/**
 * Creates a store of notifications.
 *
 * Example object in the array:
 * {
 * 	id: "error-message",
 * 	type: "error", // error | warning | success | primary (default)
 * 	dismissible: true,
 * 	flash: true, // Optional, means notification is context specific and will not persist on server, defaults to true.
 * 	inline: false, // Optional, unlikely to be true, included here for completeness.
 * 	only_show_on_tab: "media-library", // Optional, blank/missing means on all tabs.
 * 	heading: "Global Error: Something has gone terribly pear shaped.", // Optional.
 * 	message: "We're so sorry, but unfortunately we're going to have to delete the year 2020.", // Optional.
 * 	icon: "notification-error.svg", // Optional icon file name to be shown in front of heading.
 * 	plainHeading: false, // Optional boolean as to whether a <p> tag should be used instead of <h3> for heading content.
 * 	extra: "", // Optional extra content to be shown in paragraph below message.
 * 	links: [], // Optional list of links to be shown at bottom of notice.
 * },
 *
 * @return {Object}
 */
function createNotifications() {
	const { subscribe, set, update } = writable( [] );

	return {
		set,
		subscribe,
		add( notification ) {
			// There's a slight difference between our notification's formatting and what WP uses.
			if ( notification.hasOwnProperty( "type" ) && notification.type === "updated" ) {
				notification.type = "success";
			}
			if ( notification.hasOwnProperty( "type" ) && notification.type === "notice-warning" ) {
				notification.type = "warning";
			}
			if ( notification.hasOwnProperty( "type" ) && notification.type === "notice-info" ) {
				notification.type = "info";
			}
			if (
				notification.hasOwnProperty( "message" ) &&
				(!notification.hasOwnProperty( "heading" ) || notification.heading.trim().length === 0)
			) {
				notification.heading = notification.message;
				notification.plainHeading = true;
				delete notification.message;
			}
			if ( !notification.hasOwnProperty( "flash" ) ) {
				notification.flash = true;
			}

			// We need some sort of id for indexing and to ensure rendering is efficient.
			if ( !notification.hasOwnProperty( "id" ) ) {
				// Notifications are useless without at least a heading or message, so we can be sure at least one exists.
				const idHeading = notification.hasOwnProperty( "heading" ) ? notification.heading.trim() : "dynamic-heading";
				const idMessage = notification.hasOwnProperty( "message" ) ? notification.message.trim() : "dynamic-message";

				notification.id = btoa( idHeading + idMessage );
			}

			// So that rendering is efficient, but updates displayed notifications that re-use keys,
			// we create a render_key based on id and created_at as created_at is churned on re-use.
			const createdAt = notification.hasOwnProperty( "created_at" ) ? notification.created_at : 0;
			notification.render_key = notification.id + "-" + createdAt;

			update( $notifications => {
				// Maybe update a notification if id already exists.
				let index = -1;
				if ( notification.hasOwnProperty( "id" ) ) {
					index = $notifications.findIndex( _notification => _notification.id === notification.id );
				}

				if ( index >= 0 ) {
					// If the id exists but has been dismissed, add the replacement notification to the end of the array
					// if given notification is newer, otherwise skip it entirely.
					if ( $notifications[ index ].hasOwnProperty( "dismissed" ) ) {
						if ( $notifications[ index ].dismissed < notification.created_at ) {
							$notifications.push( notification );
							$notifications.splice( index, 1 );
						}
					} else {
						// Update existing.
						$notifications.splice( index, 1, notification );
					}
				} else {
					// Add new.
					$notifications.push( notification );
				}

				return $notifications.sort( this.sortCompare );
			} );
		},
		sortCompare( a, b ) {
			// Sort by created_at in case an existing notification was updated.
			if ( a.created_at < b.created_at ) {
				return -1;
			}

			if ( a.created_at > b.created_at ) {
				return 1;
			}

			return 0;
		},
		async dismiss( id ) {
			update( $notifications => {
				const index = $notifications.findIndex( notification => notification.id === id );

				// If the notification still exists, set a "dismissed" tombstone with the created_at value.
				// The cleanup will delete any notifications that have been dismissed and no longer exist
				// in the list of notifications retrieved from the server.
				// The created_at value ensures that if a notification is retrieved from the server that
				// has the same id but later created_at, then it can be added, otherwise it is skipped.
				if ( index >= 0 ) {
					if ( $notifications[ index ].hasOwnProperty( "created_at" ) ) {
						$notifications[ index ].dismissed = $notifications[ index ].created_at;
					} else {
						// Notification likely did not come from server, maybe a local "flash" notification.
						$notifications.splice( index, 1 );
					}
				}

				return $notifications;
			} );

			// Tell server to dismiss notification, still ok to try if flash notification, makes sure it is definitely removed.
			await api.delete( "notifications", { id: id, all_tabs: true } );
		},
		/**
		 * Delete removes a notification from the UI without telling the server.
		 */
		delete( id ) {
			update( $notifications => {
				const index = $notifications.findIndex( notification => notification.id === id );

				if ( index >= 0 ) {
					$notifications.splice( index, 1 );
				}

				return $notifications;
			} );
		},
		cleanup( latest ) {
			update( $notifications => {
				for ( const [index, notification] of $notifications.entries() ) {
					// Only clean up dismissed or server created notices that no longer exist.
					if ( notification.hasOwnProperty( "dismissed" ) || notification.hasOwnProperty( "created_at" ) ) {
						const latestIndex = latest.findIndex( _notification => _notification.id === notification.id );

						// If server doesn't know about the notification anymore, remove it.
						if ( latestIndex < 0 ) {
							$notifications.splice( index, 1 );
						}
					}
				}

				return $notifications;
			} );
		}
	};
}

const notifications = createNotifications();

// Controller for periodic fetch of state info.
let stateFetchInterval;
let stateFetchIntervalStarted = false;
let stateFetchIntervalPaused = false;

// Store of functions to call before an update of state processes the result into config.
const preStateUpdateCallbacks = writable( [] );

// Store of functions to call after an update of state processes the result into config.
const postStateUpdateCallbacks = writable( [] );

/**
 * Store of functions to call when state info is updated, and actual API access methods.
 *
 * Functions are called after the returned state info has been used to update the config store.
 * Therefore, functions should only be added to the store if extra processing is required.
 * The functions should be asynchronous as they are part of the reactive chain and called with await.
 *
 * @return {Object}
 */
function createAppState() {
	const { subscribe, set, update } = writable( [] );

	return {
		subscribe,
		set,
		update,
		async fetch() {
			const json = await api.get( "state", {} );

			// Abort controller is still a bit hit or miss, so we'll go old skool.
			if ( stateFetchIntervalStarted && !stateFetchIntervalPaused ) {
				this.updateState( json );
			}
		},
		updateState( json ) {
			for ( const callable of get$1( preStateUpdateCallbacks ) ) {
				callable( json );
			}

			const dirty = get$1( settings_changed );
			const previous_settings = { ...get$1( current_settings ) }; // cloned

			config.update( $config => {
				return { ...$config, ...json };
			} );

			// If the settings weren't changed before, they shouldn't be now.
			if ( !dirty && get$1( settings_changed ) ) {
				settings.reset();
			}

			// If settings are in middle of being changed when changes come in
			// from server, reset to server version.
			if ( dirty && objectsDiffer( [previous_settings, get$1( current_settings )] ) ) {
				needs_refresh.update( $needs_refresh => true );
				settings.reset();
			}

			for ( const callable of get$1( postStateUpdateCallbacks ) ) {
				callable( json );
			}
		},
		async startPeriodicFetch() {
			stateFetchIntervalStarted = true;
			stateFetchIntervalPaused = false;

			await this.fetch();

			stateFetchInterval = setInterval( async () => {
				await this.fetch();
			}, 5000 );
		},
		stopPeriodicFetch() {
			stateFetchIntervalStarted = false;
			stateFetchIntervalPaused = false;

			clearInterval( stateFetchInterval );
		},
		pausePeriodicFetch() {
			if ( stateFetchIntervalStarted ) {
				stateFetchIntervalPaused = true;
				clearInterval( stateFetchInterval );
			}
		},
		async resumePeriodicFetch() {
			stateFetchIntervalPaused = false;

			if ( stateFetchIntervalStarted ) {
				await this.startPeriodicFetch();
			}
		}
	};
}

const appState = createAppState();

// API functions added here to avoid JSHint errors.
api.headers = () => {
	return {
		"Accept": "application/json",
		"Content-Type": "application/json",
		"X-WP-Nonce": get$1( nonce )
	};
};

api.url = ( endpoint ) => {
	return get$1( urls ).api + get$1( endpoints )[ endpoint ];
};

api.get = async ( endpoint, params ) => {
	let url = new URL( api.url( endpoint ) );

	const searchParams = new URLSearchParams( params );

	searchParams.forEach( function( value, name ) {
		url.searchParams.set( name, value );
	} );

	const response = await fetch( url.toString(), {
		method: "GET",
		headers: api.headers()
	} );
	return response.json().then( json => {
		json = api.check_response( json );
		return json;
	} );
};

api.post = async ( endpoint, body ) => {
	const response = await fetch( api.url( endpoint ), {
		method: "POST",
		headers: api.headers(),
		body: JSON.stringify( body )
	} );
	return response.json().then( json => {
		json = api.check_response( json );
		return json;
	} );
};

api.put = async ( endpoint, body ) => {
	const response = await fetch( api.url( endpoint ), {
		method: "PUT",
		headers: api.headers(),
		body: JSON.stringify( body )
	} );
	return response.json().then( json => {
		json = api.check_response( json );
		return json;
	} );
};

api.delete = async ( endpoint, body ) => {
	const response = await fetch( api.url( endpoint ), {
		method: "DELETE",
		headers: api.headers(),
		body: JSON.stringify( body )
	} );
	return response.json().then( json => {
		json = api.check_response( json );
		return json;
	} );
};

api.check_errors = ( json ) => {
	if ( json.code && json.message ) {
		notifications.add( {
			id: json.code,
			type: "error",
			dismissible: true,
			heading: get$1( strings ).api_error_notice_heading,
			message: json.message
		} );

		// Just in case resultant json is expanded into a store.
		delete json.code;
		delete json.message;
	}

	return json;
};

api.check_notifications = ( json ) => {
	const _notifications = json.hasOwnProperty( "notifications" ) ? json.notifications : [];
	if ( _notifications ) {
		for ( const notification of _notifications ) {
			notifications.add( notification );
		}
	}
	notifications.cleanup( _notifications );

	// Just in case resultant json is expanded into a store.
	delete json.notifications;

	return json;
};

api.check_response = ( json ) => {
	json = api.check_notifications( json );
	json = api.check_errors( json );

	return json;
};

enable_legacy_mode_flag();

function parse(str, loose) {
	if (str instanceof RegExp) return { keys:false, pattern:str };
	var c, o, tmp, ext, keys=[], pattern='', arr = str.split('/');
	arr[0] || arr.shift();

	while (tmp = arr.shift()) {
		c = tmp[0];
		if (c === '*') {
			keys.push('wild');
			pattern += '/(.*)';
		} else if (c === ':') {
			o = tmp.indexOf('?', 1);
			ext = tmp.indexOf('.', 1);
			keys.push( tmp.substring(1, !!~o ? o : !!~ext ? ext : tmp.length) );
			pattern += !!~o && !~ext ? '(?:/([^/]+?))?' : '/([^/]+?)';
			if (!!~ext) pattern += (!!~o ? '?' : '') + '\\' + tmp.substring(ext);
		} else {
			pattern += '/' + tmp;
		}
	}

	return {
		keys: keys,
		pattern: new RegExp('^' + pattern + (loose ? '(?=$|\/)' : '\/?$'), 'i')
	};
}

Router[FILENAME] = 'node_modules/svelte-spa-router/Router.svelte';

function getLocation() {
	const hashPosition = window.location.href.indexOf('#/');
	let location = hashPosition > -1 ? window.location.href.substr(hashPosition + 1) : '/';

	// Check if there's a querystring
	const qsPosition = location.indexOf('?');

	let querystring = '';

	if (qsPosition > -1) {
		querystring = location.substr(qsPosition + 1);
		location = location.substr(0, qsPosition);
	}

	return { location, querystring };
}

/**
 * Readable store that returns the current full location (incl. querystring)
 */
const loc = readable(
	null,
	// eslint-disable-next-line prefer-arrow-callback
	function start(set) {
		set(getLocation());

		const update = () => {
			set(getLocation());
		};

		window.addEventListener('hashchange', update, false);

		return function stop() {
			window.removeEventListener('hashchange', update, false);
		};
	}
);

/**
 * Readable store that returns the current location
 */
const location$1 = derived$1(loc, (_loc) => _loc.location);

/**
 * Readable store that returns the current querystring
 */
derived$1(loc, (_loc) => _loc.querystring);

/**
 * Store that returns the currently-matched params.
 * Despite this being writable, consumers should not change the value of the store.
 * It is exported as a readable store only (in the typings file)
 */
const params = writable(undefined);

/**
 * Navigates to a new page programmatically.
 *
 * @param {string} location - Path to navigate to (must start with `/` or '#/')
 * @return {Promise<void>} Promise that resolves after the page navigation has completed
 */
async function push(location) {
	if (!location || location.length < 1 || equals(location.charAt(0), '/', false) && strict_equals(location.indexOf('#/'), 0, false)) {
		throw Error('Invalid parameter location');
	}

	// Execute this code when the current call stack is complete
	(await track_reactivity_loss(tick()))();

	// Note: this will include scroll state in history even when restoreScrollState is false
	history.replaceState(
		{
			...history.state,
			__svelte_spa_router_scrollX: window.scrollX,
			__svelte_spa_router_scrollY: window.scrollY
		},
		undefined
	);

	window.location.hash = (equals(location.charAt(0), '#') ? '' : '#') + location;
}

/**
 * Dictionary with options for the link action.
 * @typedef {Object} LinkActionOpts
 * @property {string} href - A string to use in place of the link's href attribute. Using this allows for updating link's targets reactively.
 * @property {boolean} disabled - If true, link is disabled
 */
/**
 * Svelte Action that enables a link element (`<a>`) to use our history management.
 *
 * For example:
 *
 * ````html
 * <a href="/books" use:link>View books</a>
 * ````
 *
 * @param {HTMLElement} node - The target node (automatically set by Svelte). Must be an anchor tag (`<a>`) with a href attribute starting in `/`
 * @param {string|LinkActionOpts} opts - Options object. For legacy reasons, we support a string too which will be the value for opts.href
 */
function link(node, opts) {
	opts = linkOpts(opts);

	// Only apply to <a> tags
	if (!node || !node.tagName || equals(node.tagName.toLowerCase(), 'a', false)) {
		throw Error('Action "link" can only be used with <a> tags');
	}

	updateLink(node, opts);

	return {
		update(updated) {
			updated = linkOpts(updated);
			updateLink(node, updated);
		}
	};
}

/**
 * Tries to restore the scroll state from the given history state.
 *
 * @param {{__svelte_spa_router_scrollX: number, __svelte_spa_router_scrollY: number}} [state] - The history state to restore from.
 */
function restoreScroll(state) {
	// If this exists, then this is a back navigation: restore the scroll position
	if (state) {
		window.scrollTo(state.__svelte_spa_router_scrollX, state.__svelte_spa_router_scrollY);
	} else {
		// Otherwise this is a forward navigation: scroll to top
		window.scrollTo(0, 0);
	}
}

// Internal function used by the link function
function updateLink(node, opts) {
	let href = opts.href || node.getAttribute('href');

	// Destination must start with '/' or '#/'
	if (href && equals(href.charAt(0), '/')) {
		// Add # to the href attribute
		href = '#' + href;
	} else if (!href || href.length < 2 || equals(href.slice(0, 2), '#/', false)) {
		throw Error('Invalid value for "href" attribute: ' + href);
	}

	node.setAttribute('href', href);

	node.addEventListener('click', (event) => {
		// Prevent default anchor onclick behaviour
		event.preventDefault();

		if (!opts.disabled) {
			scrollstateHistoryHandler(event.currentTarget.getAttribute('href'));
		}
	});
}

// Internal function that ensures the argument of the link action is always an object
function linkOpts(val) {
	if (val && equals(typeof val, 'string')) {
		return { href: val };
	} else {
		return val || {};
	}
}

/**
 * The handler attached to an anchor tag responsible for updating the
 * current history state with the current scroll state
 *
 * @param {string} href - Destination
 */
function scrollstateHistoryHandler(href) {
	// Setting the url (3rd arg) to href will break clicking for reasons, so don't try to do that
	history.replaceState(
		{
			...history.state,
			__svelte_spa_router_scrollX: window.scrollX,
			__svelte_spa_router_scrollY: window.scrollY
		},
		undefined
	);

	// This will force an update as desired, but this time our scroll state will be attached
	window.location.hash = href;
}

function Router($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, Router);

	/**
	 * Dictionary of all routes, in the format `'/path': component`.
	 *
	 * For example:
	 * ````js
	 * import HomeRoute from './routes/HomeRoute.svelte'
	 * import BooksRoute from './routes/BooksRoute.svelte'
	 * import NotFoundRoute from './routes/NotFoundRoute.svelte'
	 * routes = {
	 *     '/': HomeRoute,
	 *     '/books': BooksRoute,
	 *     '*': NotFoundRoute
	 * }
	 * ````
	 */
	let routes = prop($$props, 'routes', 24, () => ({}));

	/**
	 * Optional prefix for the routes in this router. This is useful for example in the case of nested routers.
	 */
	let prefix = prop($$props, 'prefix', 8, '');

	/**
	 * If set to true, the router will restore scroll positions on back navigation
	 * and scroll to top on forward navigation.
	 */
	let restoreScrollState = prop($$props, 'restoreScrollState', 8, false);

	/**
	 * Container for a route: path, component
	 */
	class RouteItem {
		/**
		 * Initializes the object and creates a regular expression from the path, using regexparam.
		 *
		 * @param {string} path - Path to the route (must start with '/' or '*')
		 * @param {SvelteComponent|WrappedComponent} component - Svelte component for the route, optionally wrapped
		 */
		constructor(path, component) {
			if (!component || equals(typeof component, 'function', false) && (equals(typeof component, 'object', false) || strict_equals(component._sveltesparouter, true, false))) {
				throw Error('Invalid component object');
			}

			// Path must be a regular or expression, or a string starting with '/' or '*'
			if (!path || equals(typeof path, 'string') && (path.length < 1 || equals(path.charAt(0), '/', false) && equals(path.charAt(0), '*', false)) || equals(typeof path, 'object') && !(path instanceof RegExp)) {
				throw Error('Invalid value for "path" argument - strings must start with / or *');
			}

			const { pattern, keys } = parse(path);

			this.path = path;

			// Check if the component is wrapped and we have conditions
			if (equals(typeof component, 'object') && strict_equals(component._sveltesparouter, true)) {
				this.component = component.component;
				this.conditions = component.conditions || [];
				this.userData = component.userData;
				this.props = component.props || {};
			} else {
				// Convert the component to a function that returns a Promise, to normalize it
				this.component = () => Promise.resolve(component);

				this.conditions = [];
				this.props = {};
			}

			this._pattern = pattern;
			this._keys = keys;
		}

		/**
		 * Checks if `path` matches the current route.
		 * If there's a match, will return the list of parameters from the URL (if any).
		 * In case of no match, the method will return `null`.
		 *
		 * @param {string} path - Path to test
		 * @returns {null|Object.<string, string>} List of paramters from the URL if there's a match, or `null` otherwise.
		 */
		match(path) {
			// If there's a prefix, check if it matches the start of the path.
			// If not, bail early, else remove it before we run the matching.
			if (prefix()) {
				if (equals(typeof prefix(), 'string')) {
					if (path.startsWith(prefix())) {
						path = path.substr(prefix().length) || '/';
					} else {
						return null;
					}
				} else if (prefix() instanceof RegExp) {
					const match = path.match(prefix());

					if (match && match[0]) {
						path = path.substr(match[0].length) || '/';
					} else {
						return null;
					}
				}
			}

			// Check if the pattern matches
			const matches = this._pattern.exec(path);

			if (strict_equals(matches, null)) {
				return null;
			}

			// If the input was a regular expression, this._keys would be false, so return matches as is
			if (strict_equals(this._keys, false)) {
				return matches;
			}

			const out = {};
			let i = 0;

			while (i < this._keys.length) {
				// In the match parameters, URL-decode all values
				try {
					out[this._keys[i]] = decodeURIComponent(matches[i + 1] || '') || null;
				} catch(e) {
					out[this._keys[i]] = null;
				}

				i++;
			}

			return out;
		}

		/**
		 * Dictionary with route details passed to the pre-conditions functions, as well as the `routeLoading`, `routeLoaded` and `conditionsFailed` events
		 * @typedef {Object} RouteDetail
		 * @property {string|RegExp} route - Route matched as defined in the route definition (could be a string or a reguar expression object)
		 * @property {string} location - Location path
		 * @property {string} querystring - Querystring from the hash
		 * @property {object} [userData] - Custom data passed by the user
		 * @property {SvelteComponent} [component] - Svelte component (only in `routeLoaded` events)
		 * @property {string} [name] - Name of the Svelte component (only in `routeLoaded` events)
		 */
		/**
		 * Executes all conditions (if any) to control whether the route can be shown. Conditions are executed in the order they are defined, and if a condition fails, the following ones aren't executed.
		 * 
		 * @param {RouteDetail} detail - Route detail
		 * @returns {boolean} Returns true if all the conditions succeeded
		 */
		async checkConditions(detail) {
			for (let i = 0; i < this.conditions.length; i++) {
				if (!(await track_reactivity_loss(this.conditions[i](detail)))()) {
					return false;
				}
			}

			return true;
		}
	}

	// Set up all routes
	const routesList = [];

	if (routes() instanceof Map) {
		// If it's a map, iterate on it right away
		routes().forEach((route, path) => {
			routesList.push(new RouteItem(path, route));
		});
	} else {
		// We have an object, so iterate on its own properties
		Object.keys(routes()).forEach((path) => {
			routesList.push(new RouteItem(path, routes()[path]));
		});
	}

	// Props for the component to render
	let component$1 = mutable_source(null);

	let componentParams = mutable_source(null);
	let props = mutable_source({});

	// Event dispatcher from Svelte
	const dispatch = createEventDispatcher();

	// Just like dispatch, but executes on the next iteration of the event loop
	async function dispatchNextTick(name, detail) {
		// Execute this code when the current call stack is complete
		(await track_reactivity_loss(tick()))();

		dispatch(name, detail);
	}

	// If this is set, then that means we have popped into this var the state of our last scroll position
	let previousScrollState = null;

	// Update history.scrollRestoration depending on restoreScrollState
	let popStateChanged = null;

	if (restoreScrollState()) {
		popStateChanged = (event) => {
			// If this event was from our history.replaceState, event.state will contain
			// our scroll history. Otherwise, event.state will be null (like on forward
			// navigation)
			if (event.state && (event.state.__svelte_spa_router_scrollY || event.state.__svelte_spa_router_scrollX)) {
				previousScrollState = event.state;
			} else {
				previousScrollState = null;
			}
		};

		// This is removed in the destroy() invocation below
		window.addEventListener('popstate', popStateChanged);

		afterUpdate(() => {
			restoreScroll(previousScrollState);
		});
	}

	// Always have the latest value of loc
	let lastLoc = null;

	// Current object of the component loaded
	let componentObj = null;

	// Handle hash change events
	// Listen to changes in the $loc store and update the page
	// Do not use the $: syntax because it gets triggered by too many things
	const unsubscribeLoc = loc.subscribe(async (newLoc) => {
		lastLoc = newLoc;

		// Find a route matching the location
		let i = 0;

		while (i < routesList.length) {
			const match = routesList[i].match(newLoc.location);

			if (!match) {
				i++;

				continue;
			}

			const detail = {
				route: routesList[i].path,
				location: newLoc.location,
				querystring: newLoc.querystring,
				userData: routesList[i].userData,
				params: match && equals(typeof match, 'object') && Object.keys(match).length ? match : null
			};

			// Check if the route can be loaded - if all conditions succeed
			if (!(await track_reactivity_loss(routesList[i].checkConditions(detail)))()) {
				// Don't display anything
				set(component$1, null);

				componentObj = null;

				// Trigger an event to notify the user, then exit
				dispatchNextTick('conditionsFailed', detail);

				return;
			}

			// Trigger an event to alert that we're loading the route
			// We need to clone the object on every event invocation so we don't risk the object to be modified in the next tick
			dispatchNextTick('routeLoading', Object.assign({}, detail));

			// If there's a component to show while we're loading the route, display it
			const obj = routesList[i].component;

			// Do not replace the component if we're loading the same one as before, to avoid the route being unmounted and re-mounted
			if (equals(componentObj, obj, false)) {
				if (obj.loading) {
					set(component$1, obj.loading);
					componentObj = obj;
					set(componentParams, obj.loadingParams);
					set(props, {});

					// Trigger the routeLoaded event for the loading component
					// Create a copy of detail so we don't modify the object for the dynamic route (and the dynamic route doesn't modify our object too)
					dispatchNextTick('routeLoaded', Object.assign({}, detail, {
						component: get(component$1),
						name: get(component$1).name,
						params: get(componentParams)
					}));
				} else {
					set(component$1, null);
					componentObj = null;
				}

				// Invoke the Promise
				const loaded = (await track_reactivity_loss(obj()))();

				// Now that we're here, after the promise resolved, check if we still want this component, as the user might have navigated to another page in the meanwhile
				if (equals(newLoc, lastLoc, false)) {
					// Don't update the component, just exit
					return;
				}

				// If there is a "default" property, which is used by async routes, then pick that
				set(component$1, loaded && loaded.default || loaded);

				componentObj = obj;
			}

			// Set componentParams only if we have a match, to avoid a warning similar to `<Component> was created with unknown prop 'params'`
			// Of course, this assumes that developers always add a "params" prop when they are expecting parameters
			if (match && equals(typeof match, 'object') && Object.keys(match).length) {
				set(componentParams, match);
			} else {
				set(componentParams, null);
			}

			// Set static props, if any
			set(props, routesList[i].props);

			// Dispatch the routeLoaded event then exit
			// We need to clone the object on every event invocation so we don't risk the object to be modified in the next tick
			dispatchNextTick('routeLoaded', Object.assign({}, detail, {
				component: get(component$1),
				name: get(component$1).name,
				params: get(componentParams)
			})).then(() => {
				params.set(get(componentParams));
			});

			return;
		}

		// If we're still here, there was no match, so show the empty component
		set(component$1, null);

		componentObj = null;
		params.set(undefined);
	});

	onDestroy(() => {
		unsubscribeLoc();
		popStateChanged && window.removeEventListener('popstate', popStateChanged);
	});

	legacy_pre_effect(() => (deep_read_state(restoreScrollState())), () => {
		history.scrollRestoration = restoreScrollState() ? 'manual' : 'auto';
	});

	legacy_pre_effect_reset();

	var $$exports = { ...legacy_api() };

	init();

	var fragment = comment();
	var node_1 = first_child(fragment);

	{
		var consequent = ($$anchor) => {
			var fragment_1 = comment();
			var node_2 = first_child(fragment_1);

			add_svelte_meta(
				() => component(node_2, () => get(component$1), ($$anchor, $$component) => {
					$$component($$anchor, spread_props(
						{
							get params() {
								return get(componentParams);
							}
						},
						() => get(props),
						{
							$$events: {
								routeEvent($$arg) {
									bubble_event.call(this, $$props, $$arg);
								}
							}
						}
					));
				}),
				'component',
				Router,
				240,
				4,
				{ componentTag: 'svelte:component' }
			);

			append($$anchor, fragment_1);
		};

		var alternate = ($$anchor) => {
			var fragment_2 = comment();
			var node_3 = first_child(fragment_2);

			add_svelte_meta(
				() => component(node_3, () => get(component$1), ($$anchor, $$component) => {
					$$component($$anchor, spread_props(() => get(props), {
						$$events: {
							routeEvent($$arg) {
								bubble_event.call(this, $$props, $$arg);
							}
						}
					}));
				}),
				'component',
				Router,
				247,
				4,
				{ componentTag: 'svelte:component' }
			);

			append($$anchor, fragment_2);
		};

		add_svelte_meta(
			() => if_block(node_1, ($$render) => {
				if (get(componentParams)) $$render(consequent); else $$render(alternate, -1);
			}),
			'if',
			Router,
			239,
			0
		);
	}

	append($$anchor, fragment);

	return pop($$exports);
}

/**
 * @typedef {Object} WrappedComponent Object returned by the `wrap` method
 * @property {SvelteComponent} component - Component to load (this is always asynchronous)
 * @property {RoutePrecondition[]} [conditions] - Route pre-conditions to validate
 * @property {Object} [props] - Optional dictionary of static props
 * @property {Object} [userData] - Optional user data dictionary
 * @property {bool} _sveltesparouter - Internal flag; always set to true
 */

/**
 * @callback AsyncSvelteComponent
 * @returns {Promise<SvelteComponent>} Returns a Promise that resolves with a Svelte component
 */

/**
 * @callback RoutePrecondition
 * @param {RouteDetail} detail - Route detail object
 * @returns {boolean|Promise<boolean>} If the callback returns a false-y value, it's interpreted as the precondition failed, so it aborts loading the component (and won't process other pre-condition callbacks)
 */

/**
 * @typedef {Object} WrapOptions Options object for the call to `wrap`
 * @property {SvelteComponent} [component] - Svelte component to load (this is incompatible with `asyncComponent`)
 * @property {AsyncSvelteComponent} [asyncComponent] - Function that returns a Promise that fulfills with a Svelte component (e.g. `{asyncComponent: () => import('Foo.svelte')}`)
 * @property {SvelteComponent} [loadingComponent] - Svelte component to be displayed while the async route is loading (as a placeholder); when unset or false-y, no component is shown while component
 * @property {object} [loadingParams] - Optional dictionary passed to the `loadingComponent` component as params (for an exported prop called `params`)
 * @property {object} [userData] - Optional object that will be passed to events such as `routeLoading`, `routeLoaded`, `conditionsFailed`
 * @property {object} [props] - Optional key-value dictionary of static props that will be passed to the component. The props are expanded with {...props}, so the key in the dictionary becomes the name of the prop.
 * @property {RoutePrecondition[]|RoutePrecondition} [conditions] - Route pre-conditions to add, which will be executed in order
 */

/**
 * Wraps a component to enable multiple capabilities:
 * 1. Using dynamically-imported component, with (e.g. `{asyncComponent: () => import('Foo.svelte')}`), which also allows bundlers to do code-splitting.
 * 2. Adding route pre-conditions (e.g. `{conditions: [...]}`)
 * 3. Adding static props that are passed to the component
 * 4. Adding custom userData, which is passed to route events (e.g. route loaded events) or to route pre-conditions (e.g. `{userData: {foo: 'bar}}`)
 * 
 * @param {WrapOptions} args - Arguments object
 * @returns {WrappedComponent} Wrapped component
 */
function wrap(args) {
    if (!args) {
        throw Error('Parameter args is required')
    }

    // We need to have one and only one of component and asyncComponent
    // This does a "XNOR"
    if (!args.component == !args.asyncComponent) {
        throw Error('One and only one of component and asyncComponent is required')
    }

    // If the component is not async, wrap it into a function returning a Promise
    if (args.component) {
        args.asyncComponent = () => Promise.resolve(args.component);
    }

    // Parameter asyncComponent and each item of conditions must be functions
    if (typeof args.asyncComponent != 'function') {
        throw Error('Parameter asyncComponent must be a function')
    }
    if (args.conditions) {
        // Ensure it's an array
        if (!Array.isArray(args.conditions)) {
            args.conditions = [args.conditions];
        }
        for (let i = 0; i < args.conditions.length; i++) {
            if (!args.conditions[i] || typeof args.conditions[i] != 'function') {
                throw Error('Invalid parameter conditions[' + i + ']')
            }
        }
    }

    // Check if we have a placeholder component
    if (args.loadingComponent) {
        args.asyncComponent.loading = args.loadingComponent;
        args.asyncComponent.loadingParams = args.loadingParams || undefined;
    }

    // Returns an object that contains all the functions to execute too
    // The _sveltesparouter flag is to confirm the object was created by this router
    const obj = {
        component: args.asyncComponent,
        userData: args.userData,
        conditions: (args.conditions && args.conditions.length) ? args.conditions : undefined,
        props: (args.props && Object.keys(args.props).length) ? args.props : {},
        _sveltesparouter: true
    };

    return obj
}

/**
 * Creates store of default pages.
 *
 * Having a title means inclusion in main tabs.
 *
 * @return {Object}
 */
function createPages() {
	// NOTE: get() only resolves after initialization, hence arrow functions for getting titles.
	const { subscribe, set, update } = writable( [] );

	return {
		subscribe,
		set,
		add( page ) {
			update( $pages => {
				return [...$pages, page]
					.sort( ( a, b ) => {
						return a.position - b.position;
					} );
			} );
		},
		withPrefix( prefix = null ) {
			return get$1( this ).filter( ( page ) => {
				return (prefix && page.route.startsWith( prefix )) || !prefix;
			} );
		},
		routes( prefix = null ) {
			let defaultComponent = null;
			let defaultUserData = null;
			const routes = new Map();

			// If a page can be enabled/disabled, check whether it is enabled before displaying.
			const conditions = [
				( detail ) => {
					if (
						detail.hasOwnProperty( "userData" ) &&
						detail.userData.hasOwnProperty( "page" ) &&
						detail.userData.page.hasOwnProperty( "enabled" )
					) {
						return detail.userData.page.enabled();
					}

					return true;
				}
			];

			for ( const page of this.withPrefix( prefix ) ) {
				const userData = { page: page };

				let route = page.route;

				if ( prefix && route !== prefix + "/*" ) {
					route = route.replace( prefix, "" );
				}

				routes.set( route, wrap( {
					component: page.component,
					userData: userData,
					conditions: conditions,
					props: {
						onRouteEvent: this.onRouteEvent.bind( this )
					}
				} ) );

				if ( !defaultComponent && page.default ) {
					defaultComponent = page.component;
					defaultUserData = userData;
				}
			}

			if ( defaultComponent ) {
				routes.set( "*", wrap( {
					component: defaultComponent,
					userData: defaultUserData,
					conditions: conditions,
					props: {
						onRouteEvent: this.onRouteEvent.bind( this )
					}
				} ) );
			}

			return routes;
		},
		handleRouteEvent( detail ) {
			if ( detail.hasOwnProperty( "event" ) ) {
				if ( !detail.hasOwnProperty( "data" ) ) {
					detail.data = {};
				}

				// Find the first page that wants to handle the event
				// , but also let other pages see the event
				// so they can set any initial state etc.
				let route = false;
				for ( const page of get$1( this ).values() ) {
					if ( page.events && page.events[ detail.event ] && page.events[ detail.event ]( detail.data ) && !route ) {
						route = page.route;
					}
				}

				if ( route ) {
					return route;
				}
			}

			if ( detail.hasOwnProperty( "default" ) ) {
				return detail.default;
			}

			return false;
		},
		/**
		 * Handles events published by the router.
		 *
		 * This handler gives pages a chance to put their hand up and
		 * provide a new route to be navigated to in response
		 * to some event.
		 * e.g. settings saved resulting in a question being asked.
		 *
		 * @param {Object} event
		 */
		onRouteEvent( event ) {
			const route = this.handleRouteEvent( event );

			if ( route ) {
				push( route );
			}
		}
	};
}

const pages = createPages();

// Convenience readable store of all routes.
const routes = derived$1( pages, () => {
	return pages.routes();
} );

Page[FILENAME] = 'ui/components/Page.svelte';

var root$w = add_locations(from_html(`<div><!></div>`), Page[FILENAME], [[41, 0]]);

function Page($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Page);

	const $current_settings = () => (
		validate_store(current_settings, 'current_settings'),
		store_get(current_settings, '$current_settings', $$stores)
	);

	const $location = () => (
		validate_store(location$1, 'location'),
		store_get(location$1, '$location', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {boolean} [subpage] - In some scenarios a Page should have some SubPage behaviours.
	 * @property {any} [initialSettings]
	 * @property {import("svelte").Snippet} [children]
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, ""),
		subpage = prop($$props, 'subpage', 3, false),
		initialSettings = prop($$props, 'initialSettings', 19, $current_settings);

	// When a page is created, store a copy of the initial settings
	// so they can be compared with any changes later.
	// svelte-ignore state_referenced_locally
	setContext("initialSettings", initialSettings());

	// Tell the route event handlers about the initial settings too.
	onMount(() => {
		$$props.onRouteEvent({
			event: "page.initial.settings",
			data: { settings: initialSettings(), location: $location() }
		});
	});

	var $$exports = { ...legacy_api() };
	var div = root$w();
	let classes;
	var node = child(div);

	add_svelte_meta(() => snippet(node, () => $$props.children ?? noop), 'render', Page, 42, 1);
	template_effect(() => classes = set_class(div, 1, `page-wrapper ${name() ?? ''}`, null, classes, { subpage: subpage() }));
	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

Button[FILENAME] = 'ui/components/Button.svelte';

var root_1$u = add_locations(from_html(`<img/>`), Button[FILENAME], [[86, 2]]);
var root$v = add_locations(from_html(`<button><!> <!></button>`), Button[FILENAME], [[65, 0]]);

function Button($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Button);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {any} [ref]
	 * @property {boolean} [extraSmall] - Button sizes, medium is the default.
	 * @property {boolean} [small]
	 * @property {boolean} [large]
	 * @property {any} [medium]
	 * @property {boolean} [primary] - Button styles, outline is the default.
	 * @property {boolean} [expandable]
	 * @property {boolean} [refresh]
	 * @property {any} [outline]
	 * @property {boolean} [disabled] - Is the button disabled? Defaults to false.
	 * @property {boolean} [expanded] - Is the button in an expanded state? Defaults to false.
	 * @property {boolean} [refreshing] - Is the button in a refreshing state? Defaults to false.
	 * @property {string} [title] - A button can have a title, most useful to give a reason when disabled.
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 * @property {function} [onclick]
	 * @property {function} [onfocusout]
	 * @property {function} [onCancel] - Callback for custom cancel event.
	 */
	/** @type {Props} */
	let ref = prop($$props, 'ref', 31, () => tag_proxy(proxy({}), 'ref')),
		extraSmall = prop($$props, 'extraSmall', 3, false),
		small = prop($$props, 'small', 3, false),
		large = prop($$props, 'large', 3, false),
		medium = prop($$props, 'medium', 19, () => !extraSmall() && !small() && !large()),
		primary = prop($$props, 'primary', 3, false),
		expandable = prop($$props, 'expandable', 3, false),
		refresh = prop($$props, 'refresh', 3, false),
		outline = prop($$props, 'outline', 19, () => !primary() && !expandable() && !refresh()),
		disabled = prop($$props, 'disabled', 3, false),
		expanded = prop($$props, 'expanded', 3, false),
		refreshing = prop($$props, 'refreshing', 3, false),
		title = prop($$props, 'title', 3, ""),
		classes = prop($$props, 'class', 3, ""),
		onCancel = prop($$props, 'onCancel', 19, () => ({}));

	/**
	 * Catch escape key and emit a custom cancel event.
	 *
	 * @param {KeyboardEvent} event
	 */
	function handleKeyup(event) {
		if (strict_equals(event.key, "Escape") && strict_equals(typeof onCancel(), "function")) {
			event.preventDefault();
			onCancel()();
		}
	}

	function refreshIcon(refreshing) {
		return $urls().assets + "img/icon/" + (refreshing ? "refresh-disabled.svg" : "refresh.svg");
	}

	var $$exports = { ...legacy_api() };
	var button = root$v();
	let classes_1;
	var node = child(button);

	{
		var consequent = ($$anchor) => {
			var img = root_1$u();
			let classes_2;

			template_effect(
				($0) => {
					classes_2 = set_class(img, 1, 'icon refresh', null, classes_2, { refreshing: refreshing() });
					set_attribute(img, 'src', $0);
					set_attribute(img, 'alt', title());
				},
				[() => refreshIcon(refreshing())]
			);

			append($$anchor, img);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (refresh()) $$render(consequent);
			}),
			'if',
			Button,
			85,
			1
		);
	}

	var node_1 = sibling(node, 2);

	add_svelte_meta(() => snippet(node_1, () => $$props.children ?? noop), 'render', Button, 88, 1);
	bind_this(button, ($$value) => ref($$value), () => ref());

	template_effect(() => {
		classes_1 = set_class(button, 1, clsx(classes()), null, classes_1, {
			'btn-xs': extraSmall(),
			'btn-sm': small(),
			'btn-md': medium(),
			'btn-lg': large(),
			'btn-primary': primary(),
			'btn-outline': outline(),
			'btn-expandable': expandable(),
			'btn-disabled': disabled(),
			'btn-expanded': expanded(),
			'btn-refresh': refresh(),
			'btn-refreshing': refreshing()
		});

		set_attribute(button, 'title', title());
		button.disabled = disabled() || refreshing();
	});

	delegated('click', button, function (...$$args) {
		apply(() => $$props.onclick, this, $$args, Button, [66, 2]);
	});

	delegated('focusout', button, function (...$$args) {
		apply(() => $$props.onfocusout, this, $$args, Button, [82, 2]);
	});

	delegated('keyup', button, handleKeyup);
	append($$anchor, button);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

delegate(['click', 'focusout', 'keyup']);

Notification[FILENAME] = 'ui/components/Notification.svelte';

var root_1$t = add_locations(from_html(`<div class="icon type"><img class="icon type"/></div>`), Notification[FILENAME], [[122, 3, [[123, 4]]]]);
var root_4$6 = add_locations(from_html(`<p></p>`), Notification[FILENAME], [[131, 7]]);
var root_5$8 = add_locations(from_html(`<h3></h3>`), Notification[FILENAME], [[133, 7]]);
var root_6$5 = add_locations(from_html(`<button class="dismiss"> </button> <!>`, 1), Notification[FILENAME], [[137, 6]]);
var root_8$5 = add_locations(from_html(`<button class="icon close"></button>`), Notification[FILENAME], [[142, 6]]);
var root_2$c = add_locations(from_html(`<div class="heading"><!> <!></div>`), Notification[FILENAME], [[128, 4]]);
var root_9$4 = add_locations(from_html(`<p></p>`), Notification[FILENAME], [[148, 4]]);
var root_10$2 = add_locations(from_html(`<p class="links"></p>`), Notification[FILENAME], [[151, 4]]);
var root$u = add_locations(from_html(`<div><div class="content"><!> <div class="body"><!> <!> <!> <!></div></div> <!></div>`), Notification[FILENAME], [[108, 0, [[120, 1, [[126, 2]]]]]]);

function Notification($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Notification);

	var $$ownership_validator = create_ownership_validator($$props);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {any} [notification]
	 * @property {any} [unique_id]
	 * @property {any} [inline]
	 * @property {any} [wordpress]
	 * @property {any} [success]
	 * @property {any} [warning]
	 * @property {any} [error]
	 * @property {any} [heading]
	 * @property {any} [dismissible]
	 * @property {any} [icon]
	 * @property {any} [plainHeading]
	 * @property {any} [extra]
	 * @property {any} [links]
	 * @property {boolean} [expandable]
	 * @property {boolean} [expanded]
	 * @property {import("svelte").Snippet} [children]
	 * @property {import("svelte").Snippet} [details]
	 * @property {string} [class]
	 */
	/** @type {Props} */
	let notification = prop($$props, 'notification', 31, () => tag_proxy(proxy({}), 'notification')),
		unique_id = prop($$props, 'unique_id', 19, () => notification().id ? notification().id : ""),
		inline = prop($$props, 'inline', 19, () => notification().inline ? notification().inline : false),
		wordpress = prop($$props, 'wordpress', 19, () => notification().wordpress ? notification().wordpress : false),
		success = prop($$props, 'success', 19, () => strict_equals(notification().type, "success")),
		warning = prop($$props, 'warning', 19, () => strict_equals(notification().type, "warning")),
		error = prop($$props, 'error', 19, () => strict_equals(notification().type, "error")),
		heading = prop($$props, 'heading', 19, () => notification().hasOwnProperty("heading") && notification().heading.trim().length ? notification().heading.trim() : ""),
		dismissible = prop($$props, 'dismissible', 19, () => notification().dismissible ? notification().dismissible : false),
		icon = prop($$props, 'icon', 19, () => notification().icon ? notification().icon : false),
		plainHeading = prop($$props, 'plainHeading', 19, () => notification().plainHeading ? notification().plainHeading : false),
		extra = prop($$props, 'extra', 19, () => notification().extra ? notification().extra : ""),
		links = prop($$props, 'links', 19, () => notification().links ? notification().links : []),
		expandable = prop($$props, 'expandable', 3, false),
		expanded = prop($$props, 'expanded', 15, false),
		classes = prop($$props, 'class', 3, "");

	let info = tag(state(false), 'info');

	// It's possible to set type purely by component property,
	// but we need notification.type to be correct too.
	user_effect(() => {
		if (success()) {
			$$ownership_validator.mutation('notification', ['notification', 'type'], notification(notification().type = "success", true), 55, 3);
		} else if (warning()) {
			$$ownership_validator.mutation('notification', ['notification', 'type'], notification(notification().type = "warning", true), 57, 3);
		} else if (error()) {
			$$ownership_validator.mutation('notification', ['notification', 'type'], notification(notification().type = "error", true), 59, 3);
		} else {
			set(info, true);
			$$ownership_validator.mutation('notification', ['notification', 'type'], notification(notification().type = "info", true), 62, 3);
		}
	});

	/**
	 * Returns the icon URL for the notification.
	 *
	 * @param {string|boolean} icon
	 * @param {string} notificationType
	 *
	 * @return {string}
	 */
	function getIconURL(icon, notificationType) {
		if (icon) {
			return $urls().assets + "img/icon/" + icon;
		}

		return $urls().assets + "img/icon/notification-" + notificationType + ".svg";
	}

	let iconURL = tag(user_derived(() => getIconURL(icon(), notification().type)), 'iconURL');

	// We need to change various properties and alignments if text is multiline.
	let iconHeight = tag(state(0), 'iconHeight');

	let bodyHeight = tag(state(0), 'bodyHeight');
	let multiline = tag(user_derived(() => get(iconHeight) && get(bodyHeight) && get(bodyHeight) > get(iconHeight)), 'multiline');

	/**
	 * Builds a links row from an array of HTML links.
	 *
	 * @param {array} links
	 *
	 * @return {string}
	 */
	function getLinksHTML(links) {
		if (links.length) {
			return links.join(" ");
		}

		return "";
	}

	let linksHTML = tag(user_derived(() => getLinksHTML(links())), 'linksHTML');
	var $$exports = { ...legacy_api() };
	var div = root$u();
	let classes_1;
	var div_1 = child(div);
	var node = child(div_1);

	{
		var consequent = ($$anchor) => {
			var div_2 = root_1$t();
			var img = child(div_2);

			template_effect(() => {
				set_attribute(img, 'src', get(iconURL));
				set_attribute(img, 'alt', `${notification().type ?? ''} icon`);
			});

			bind_element_size(div_2, 'clientHeight', function set$1($$value) {
				set(iconHeight, $$value);
			});

			append($$anchor, div_2);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (get(iconURL)) $$render(consequent);
			}),
			'if',
			Notification,
			121,
			2
		);
	}

	var div_3 = sibling(node, 2);
	var node_1 = child(div_3);

	{
		var consequent_6 = ($$anchor) => {
			var div_4 = root_2$c();
			var node_2 = child(div_4);

			{
				var consequent_2 = ($$anchor) => {
					var fragment = comment();
					var node_3 = first_child(fragment);

					{
						var consequent_1 = ($$anchor) => {
							var p = root_4$6();

							html(p, heading, true);
							append($$anchor, p);
						};

						var alternate = ($$anchor) => {
							var h3 = root_5$8();

							html(h3, heading, true);
							append($$anchor, h3);
						};

						add_svelte_meta(
							() => if_block(node_3, ($$render) => {
								if (plainHeading()) $$render(consequent_1); else $$render(alternate, -1);
							}),
							'if',
							Notification,
							130,
							6
						);
					}

					append($$anchor, fragment);
				};

				add_svelte_meta(
					() => if_block(node_2, ($$render) => {
						if (heading()) $$render(consequent_2);
					}),
					'if',
					Notification,
					129,
					5
				);
			}

			var node_4 = sibling(node_2, 2);

			{
				var consequent_3 = ($$anchor) => {
					var fragment_1 = root_6$5();
					var button = first_child(fragment_1);
					var text = child(button);

					var node_5 = sibling(button, 2);

					{
						let $0 = user_derived(() => expanded() ? $strings().hide_details : $strings().show_details);

						add_svelte_meta(
							() => Button(node_5, {
								expandable: true,
								get expanded() {
									return expanded();
								},
								onclick: () => expanded(!expanded()),
								get title() {
									return get($0);
								}
							}),
							'component',
							Notification,
							138,
							6,
							{ componentTag: 'Button' }
						);
					}

					template_effect(() => set_text(text, $strings().dismiss_all));

					delegated('click', button, function click(event) {
						event.preventDefault();
						notifications.dismiss(unique_id());
					});

					append($$anchor, fragment_1);
				};

				var consequent_4 = ($$anchor) => {
					{
						let $0 = user_derived(() => expanded() ? $strings().hide_details : $strings().show_details);

						add_svelte_meta(
							() => Button($$anchor, {
								expandable: true,
								get expanded() {
									return expanded();
								},
								onclick: () => expanded(!expanded()),
								get title() {
									return get($0);
								}
							}),
							'component',
							Notification,
							140,
							6,
							{ componentTag: 'Button' }
						);
					}
				};

				var consequent_5 = ($$anchor) => {
					var button_1 = root_8$5();

					template_effect(() => set_attribute(button_1, 'title', $strings()["dismiss_notice"]));

					delegated('click', button_1, function click_1(event) {
						event.preventDefault();
						notifications.dismiss(unique_id());
					});

					append($$anchor, button_1);
				};

				add_svelte_meta(
					() => if_block(node_4, ($$render) => {
						if (dismissible() && expandable()) $$render(consequent_3); else if (expandable()) $$render(consequent_4, 1); else if (dismissible()) $$render(consequent_5, 2);
					}),
					'if',
					Notification,
					136,
					5
				);
			}
			append($$anchor, div_4);
		};

		add_svelte_meta(
			() => if_block(node_1, ($$render) => {
				if (heading() || dismissible() || expandable()) $$render(consequent_6);
			}),
			'if',
			Notification,
			127,
			3
		);
	}

	var node_6 = sibling(node_1, 2);

	add_svelte_meta(() => snippet(node_6, () => $$props.children ?? noop), 'render', Notification, 146, 3);

	var node_7 = sibling(node_6, 2);

	{
		var consequent_7 = ($$anchor) => {
			var p_1 = root_9$4();

			html(p_1, extra, true);
			append($$anchor, p_1);
		};

		add_svelte_meta(
			() => if_block(node_7, ($$render) => {
				if (extra()) $$render(consequent_7);
			}),
			'if',
			Notification,
			147,
			3
		);
	}

	var node_8 = sibling(node_7, 2);

	{
		var consequent_8 = ($$anchor) => {
			var p_2 = root_10$2();

			html(p_2, () => get(linksHTML), true);
			append($$anchor, p_2);
		};

		add_svelte_meta(
			() => if_block(node_8, ($$render) => {
				if (get(linksHTML)) $$render(consequent_8);
			}),
			'if',
			Notification,
			150,
			3
		);
	}

	var node_9 = sibling(div_1, 2);

	add_svelte_meta(() => snippet(node_9, () => $$props.details ?? noop), 'render', Notification, 155, 1);

	template_effect(() => classes_1 = set_class(div, 1, `notification ${classes() ?? ''}`, null, classes_1, {
		inline: inline(),
		wordpress: wordpress(),
		success: success(),
		warning: warning(),
		error: error(),
		info: get(info),
		multiline: get(multiline),
		expandable: expandable(),
		expanded: expanded()
	}));

	bind_element_size(div_3, 'clientHeight', function set$1($$value) {
		set(bodyHeight, $$value);
	});

	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

delegate(['click']);

Notifications[FILENAME] = 'ui/components/Notifications.svelte';

var root_5$7 = add_locations(from_html(`<p></p>`), Notifications[FILENAME], [[35, 6]]);
var root_1$s = add_locations(from_html(`<div id="notifications" class="notifications wrapper"></div>`), Notifications[FILENAME], [[29, 1]]);

function Notifications($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Notifications);

	const $notifications = () => (
		validate_store(notifications, 'notifications'),
		store_get(notifications, '$notifications', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {any} [component]
	 * @property {string} [tab]
	 * @property {string} [tabParent]
	 */
	/** @type {Props} */
	let component$1 = prop($$props, 'component', 3, Notification),
		tab = prop($$props, 'tab', 3, ""),
		tabParent = prop($$props, 'tabParent', 3, "");

	/**
	 * Render the notification or not?
	 */
	function renderNotification(notification) {
		let not_dismissed = !notification.dismissed;
		let valid_parent_tab = strict_equals(notification.only_show_on_tab, tab()) && strict_equals(notification.hide_on_parent, true, false);
		let valid_sub_tab = strict_equals(notification.only_show_on_tab, tabParent());
		let show_on_all_tabs = !notification.only_show_on_tab;

		return not_dismissed && (valid_parent_tab || valid_sub_tab || show_on_all_tabs);
	}

	var $$exports = { ...legacy_api() };
	var fragment = comment();
	var node = first_child(fragment);

	{
		var consequent_2 = ($$anchor) => {
			var div = root_1$s();

			add_svelte_meta(
				() => each(div, 5, $notifications, (notification) => notification.render_key, ($$anchor, notification) => {
					var fragment_1 = comment();
					var node_1 = first_child(fragment_1);

					{
						var consequent_1 = ($$anchor) => {
							const NotificationComponent = tag(user_derived(component$1), 'NotificationComponent');

							get(NotificationComponent);

							var fragment_2 = comment();
							var node_2 = first_child(fragment_2);

							add_svelte_meta(
								() => component(node_2, () => get(NotificationComponent), ($$anchor, NotificationComponent_1) => {
									NotificationComponent_1($$anchor, {
										get notification() {
											return get(notification);
										},

										children: wrap_snippet(Notifications, ($$anchor, $$slotProps) => {
											var fragment_3 = comment();
											var node_3 = first_child(fragment_3);

											{
												var consequent = ($$anchor) => {
													var p = root_5$7();

													html(p, () => get(notification).message, true);
													reset(p);
													append($$anchor, p);
												};

												add_svelte_meta(
													() => if_block(node_3, ($$render) => {
														if (get(notification).message) $$render(consequent);
													}),
													'if',
													Notifications,
													34,
													5
												);
											}

											append($$anchor, fragment_3);
										}),
										$$slots: { default: true }
									});
								}),
								'component',
								Notifications,
								33,
								4,
								{ componentTag: 'NotificationComponent' }
							);

							append($$anchor, fragment_2);
						};

						var d = user_derived(() => renderNotification(get(notification)));

						add_svelte_meta(
							() => if_block(node_1, ($$render) => {
								if (get(d)) $$render(consequent_1);
							}),
							'if',
							Notifications,
							31,
							3
						);
					}

					append($$anchor, fragment_1);
				}),
				'each',
				Notifications,
				30,
				2
			);
			append($$anchor, div);
		};

		var d_1 = user_derived(() => $notifications().length && Object.values($notifications()).filter((notification) => renderNotification(notification)).length);

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (get(d_1)) $$render(consequent_2);
			}),
			'if',
			Notifications,
			28,
			0
		);
	}

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

SubNavItem[FILENAME] = 'ui/components/SubNavItem.svelte';

var root_1$r = add_locations(from_html(`<div><img class="notice-icon svelte-1gifctk"/></div>`), SubNavItem[FILENAME], [[27, 3, [[28, 4]]]]);
var root$t = add_locations(from_html(`<li><a> <!></a></li>`), SubNavItem[FILENAME], [[15, 0, [[16, 1]]]]);

function SubNavItem($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, SubNavItem);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $location = () => (
		validate_store(location$1, 'location'),
		store_get(location$1, '$location', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();
	let focus = tag(state(false), 'focus');
	let hover = tag(state(false), 'hover');
	let showIcon = tag(user_derived(() => strict_equals(typeof $$props.page.noticeIcon, "string") && ["warning", "error"].includes($$props.page.noticeIcon)), 'showIcon');

	let iconUrl = tag(
		user_derived(() => get(showIcon)
			? $urls().assets + "img/icon/tab-notifier-" + $$props.page.noticeIcon + ".svg"
			: ""),
		'iconUrl'
	);

	var $$exports = { ...legacy_api() };
	var li = root$t();
	let classes;
	var a = child(li);
	var text = child(a);
	var node = sibling(text);

	{
		var consequent = ($$anchor) => {
			var div = root_1$r();
			var img = child(div);

			template_effect(() => {
				set_class(div, 1, `notice-icon-wrapper notice-icon-${$$props.page.noticeIcon ?? ''}`, 'svelte-1gifctk');
				set_attribute(img, 'src', get(iconUrl));
				set_attribute(img, 'alt', $$props.page.noticeIcon);
			});

			append($$anchor, div);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (get(showIcon)) $$render(consequent);
			}),
			'if',
			SubNavItem,
			26,
			2
		);
	}
	action(a, ($$node) => link?.($$node));

	template_effect(
		($0, $1) => {
			classes = set_class(li, 1, 'subnav-item', null, classes, {
				active: strict_equals($location(), $$props.page.route),
				focus: get(focus),
				hover: get(hover),
				'has-icon': get(showIcon)
			});

			set_attribute(a, 'href', $$props.page.route);
			set_attribute(a, 'title', $0);
			set_text(text, `${$1 ?? ''} `);
		},
		[() => $$props.page.title(), () => $$props.page.title()]
	);

	delegated('focusin', a, function focusin() {
		return set(focus, true);
	});

	delegated('focusout', a, function focusout() {
		return set(focus, false);
	});

	event('mouseenter', a, function mouseenter() {
		return set(hover, true);
	});

	event('mouseleave', a, function mouseleave() {
		return set(hover, false);
	});

	append($$anchor, li);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

delegate(['focusin', 'focusout']);

SubNav[FILENAME] = 'ui/components/SubNav.svelte';

var root_3$8 = add_locations(from_html(`<li class="step-arrow"><img alt=""/></li>`), SubNav[FILENAME], [[30, 4, [[31, 5]]]]);
var root_2$b = add_locations(from_html(`<!> <!>`, 1), SubNav[FILENAME], []);
var root_1$q = add_locations(from_html(`<ul></ul>`), SubNav[FILENAME], [[25, 1]]);

function SubNav($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, SubNav);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {any} [items]
	 * @property {boolean} [subpage]
	 * @property {boolean} [progress]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, "media"),
		items = prop($$props, 'items', 19, () => []),
		subpage = prop($$props, 'subpage', 3, false),
		progress = prop($$props, 'progress', 3, false);

	let displayItems = tag(user_derived(() => items().filter((page) => page.title && (!page.hasOwnProperty("enabled") || strict_equals(page.enabled(), true)))), 'displayItems');
	var $$exports = { ...legacy_api() };
	var fragment = comment();
	var node = first_child(fragment);

	{
		var consequent_1 = ($$anchor) => {
			var ul = root_1$q();
			let classes;

			add_svelte_meta(
				() => each(ul, 21, () => get(displayItems), index, ($$anchor, page, index) => {
					var fragment_1 = root_2$b();
					var node_1 = first_child(fragment_1);

					add_svelte_meta(
						() => SubNavItem(node_1, {
							get page() {
								return get(page);
							}
						}),
						'component',
						SubNav,
						27,
						3,
						{ componentTag: 'SubNavItem' }
					);

					var node_2 = sibling(node_1, 2);

					{
						var consequent = ($$anchor) => {
							var li = root_3$8();
							var img = child(li);

							reset(li);
							template_effect(() => set_attribute(img, 'src', $urls().assets + 'img/icon/subnav-arrow.svg'));
							append($$anchor, li);
						};

						add_svelte_meta(
							() => if_block(node_2, ($$render) => {
								if (progress() && index < get(displayItems).length - 1) $$render(consequent);
							}),
							'if',
							SubNav,
							29,
							3
						);
					}

					append($$anchor, fragment_1);
				}),
				'each',
				SubNav,
				26,
				2
			);
			template_effect(() => classes = set_class(ul, 1, `subnav ${name() ?? ''}`, null, classes, { subpage: subpage(), progress: progress() }));
			append($$anchor, ul);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (get(displayItems)) $$render(consequent_1);
			}),
			'if',
			SubNav,
			24,
			0
		);
	}

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

SubPages[FILENAME] = 'ui/components/SubPages.svelte';

var root_1$p = add_locations(from_html(`<div><!> <!></div>`), SubPages[FILENAME], [[24, 1]]);

function SubPages($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, SubPages);

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {string} [prefix]
	 * @property {any} [routes]
	 * @property {import("svelte").Snippet} [children]
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, "sub"),
		prefix = prop($$props, 'prefix', 3, ""),
		routes = prop($$props, 'routes', 19, () => ({}));

	var $$exports = { ...legacy_api() };
	var fragment = comment();
	var node = first_child(fragment);

	{
		var consequent_1 = ($$anchor) => {
			var div = root_1$p();
			var node_1 = child(div);

			add_svelte_meta(
				() => Router(node_1, {
					get routes() {
						return routes();
					},

					get prefix() {
						return prefix();
					},

					get onRouteEvent() {
						return $$props.onRouteEvent;
					}
				}),
				'component',
				SubPages,
				25,
				2,
				{ componentTag: 'Router' }
			);

			var node_2 = sibling(node_1, 2);

			{
				var consequent = ($$anchor) => {
					var fragment_1 = comment();
					var node_3 = first_child(fragment_1);

					add_svelte_meta(() => snippet(node_3, () => $$props.children), 'render', SubPages, 27, 3);
					append($$anchor, fragment_1);
				};

				var alternate = ($$anchor) => {};

				add_svelte_meta(
					() => if_block(node_2, ($$render) => {
						if ($$props.children) $$render(consequent); else $$render(alternate, -1);
					}),
					'if',
					SubPages,
					26,
					2
				);
			}
			template_effect(() => set_class(div, 1, `${name() ?? ''}-page wrapper`));
			append($$anchor, div);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (routes()) $$render(consequent_1);
			}),
			'if',
			SubPages,
			23,
			0
		);
	}

	append($$anchor, fragment);

	return pop($$exports);
}

// List of nodes to update
const nodes = [];

// Current location
let location;

// Function that updates all nodes marking the active ones
function checkActive(el) {
    const matchesLocation = el.pattern.test(location);
    toggleClasses(el, el.className, matchesLocation);
    toggleClasses(el, el.inactiveClassName, !matchesLocation);
}

function toggleClasses(el, className, shouldAdd) {
    (className || '').split(' ').forEach((cls) => {
        if (!cls) {
            return
        }
        // Remove the class firsts
        el.node.classList.remove(cls);

        // If the pattern doesn't match, then set the class
        if (shouldAdd) {
            el.node.classList.add(cls);
        }
    });
}

// Listen to changes in the location
loc.subscribe((value) => {
    // Update the location
    location = value.location + (value.querystring ? '?' + value.querystring : '');

    // Update all nodes
    nodes.map(checkActive);
});

/**
 * @typedef {Object} ActiveOptions
 * @property {string|RegExp} [path] - Path expression that makes the link active when matched (must start with '/' or '*'); default is the link's href
 * @property {string} [className] - CSS class to apply to the element when active; default value is "active"
 */

/**
 * Svelte Action for automatically adding the "active" class to elements (links, or any other DOM element) when the current location matches a certain path.
 * 
 * @param {HTMLElement} node - The target node (automatically set by Svelte)
 * @param {ActiveOptions|string|RegExp} [opts] - Can be an object of type ActiveOptions, or a string (or regular expressions) representing ActiveOptions.path.
 * @returns {{destroy: function(): void}} Destroy function
 */
function active(node, opts) {
    // Check options
    if (opts && (typeof opts == 'string' || (typeof opts == 'object' && opts instanceof RegExp))) {
        // Interpret strings and regular expressions as opts.path
        opts = {
            path: opts
        };
    }
    else {
        // Ensure opts is a dictionary
        opts = opts || {};
    }

    // Path defaults to link target
    if (!opts.path && node.hasAttribute('href')) {
        opts.path = node.getAttribute('href');
        if (opts.path && opts.path.length > 1 && opts.path.charAt(0) == '#') {
            opts.path = opts.path.substring(1);
        }
    }

    // Default class name
    if (!opts.className) {
        opts.className = 'active';
    }

    // If path is a string, it must start with '/' or '*'
    if (!opts.path || 
        typeof opts.path == 'string' && (opts.path.length < 1 || (opts.path.charAt(0) != '/' && opts.path.charAt(0) != '*'))
    ) {
        throw Error('Invalid value for "path" argument')
    }

    // If path is not a regular expression already, make it
    const {pattern} = typeof opts.path == 'string' ?
        parse(opts.path) :
        {pattern: opts.path};

    // Add the node to the list
    const el = {
        node,
        className: opts.className,
        inactiveClassName: opts.inactiveClassName,
        pattern
    };
    nodes.push(el);

    // Trigger the action right away
    checkActive(el);

    return {
        // When the element is destroyed, remove it from the list
        destroy() {
            nodes.splice(nodes.indexOf(el), 1);
        }
    }
}

SubPage[FILENAME] = 'ui/components/SubPage.svelte';

var root$s = add_locations(from_html(`<div><!></div>`), SubPage[FILENAME], [[15, 0]]);

function SubPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, SubPage);

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {string} [route]
	 * @property {import("svelte").Snippet} [children]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, ""),
		route = prop($$props, 'route', 3, "/");

	var $$exports = { ...legacy_api() };
	var div = root$s();
	var node = child(div);

	add_svelte_meta(() => snippet(node, () => $$props.children ?? noop), 'render', SubPage, 16, 1);
	action(div, ($$node, $$action_arg) => active?.($$node, $$action_arg), route);
	template_effect(() => set_class(div, 1, name()));
	append($$anchor, div);

	return pop($$exports);
}

/** @import { BlurParams, CrossfadeParams, DrawParams, FadeParams, FlyParams, ScaleParams, SlideParams, TransitionConfig } from './public' */


/** @param {number} x */
const linear$1 = (x) => x;

/** @param {number} t */
function cubic_out(t) {
	const f = t - 1.0;
	return f * f * f + 1.0;
}

/**
 * Animates the opacity of an element from 0 to the current opacity for `in` transitions and from the current opacity to 0 for `out` transitions.
 *
 * @param {Element} node
 * @param {FadeParams} [params]
 * @returns {TransitionConfig}
 */
function fade(node, { delay = 0, duration = 400, easing = linear$1 } = {}) {
	const o = +getComputedStyle(node).opacity;
	return {
		delay,
		duration,
		easing,
		css: (t) => `opacity: ${t * o}`
	};
}

var slide_warning = false;

/**
 * Slides an element in and out.
 *
 * @param {Element} node
 * @param {SlideParams} [params]
 * @returns {TransitionConfig}
 */
function slide(node, { delay = 0, duration = 400, easing = cubic_out, axis = 'y' } = {}) {
	const style = getComputedStyle(node);

	if (DEV && !slide_warning && /(contents|inline|table)/.test(style.display)) {
		slide_warning = true;
		Promise.resolve().then(() => (slide_warning = false));
		transition_slide_display(style.display);
	}

	const opacity = +style.opacity;
	const primary_property = axis === 'y' ? 'height' : 'width';
	const primary_property_value = parseFloat(style[primary_property]);
	const secondary_properties = axis === 'y' ? ['top', 'bottom'] : ['left', 'right'];
	const capitalized_secondary_properties = secondary_properties.map(
		(e) => /** @type {'Left' | 'Right' | 'Top' | 'Bottom'} */ (`${e[0].toUpperCase()}${e.slice(1)}`)
	);
	const padding_start_value = parseFloat(style[`padding${capitalized_secondary_properties[0]}`]);
	const padding_end_value = parseFloat(style[`padding${capitalized_secondary_properties[1]}`]);
	const margin_start_value = parseFloat(style[`margin${capitalized_secondary_properties[0]}`]);
	const margin_end_value = parseFloat(style[`margin${capitalized_secondary_properties[1]}`]);
	const border_width_start_value = parseFloat(
		style[`border${capitalized_secondary_properties[0]}Width`]
	);
	const border_width_end_value = parseFloat(
		style[`border${capitalized_secondary_properties[1]}Width`]
	);
	return {
		delay,
		duration,
		easing,
		css: (t) =>
			'overflow: hidden;' +
			`opacity: ${Math.min(t * 20, 1) * opacity};` +
			`${primary_property}: ${t * primary_property_value}px;` +
			`padding-${secondary_properties[0]}: ${t * padding_start_value}px;` +
			`padding-${secondary_properties[1]}: ${t * padding_end_value}px;` +
			`margin-${secondary_properties[0]}: ${t * margin_start_value}px;` +
			`margin-${secondary_properties[1]}: ${t * margin_end_value}px;` +
			`border-${secondary_properties[0]}-width: ${t * border_width_start_value}px;` +
			`border-${secondary_properties[1]}-width: ${t * border_width_end_value}px;` +
			`min-${primary_property}: 0`
	};
}

/**
 * Animates the opacity and scale of an element. `in` transitions animate from the provided values, passed as parameters, to an element's current (default) values. `out` transitions animate from an element's default values to the provided values.
 *
 * @param {Element} node
 * @param {ScaleParams} [params]
 * @returns {TransitionConfig}
 */
function scale(
	node,
	{ delay = 0, duration = 400, easing = cubic_out, start = 0, opacity = 0 } = {}
) {
	const style = getComputedStyle(node);
	const target_opacity = +style.opacity;
	const transform = style.transform === 'none' ? '' : style.transform;
	const sd = 1 - start;
	const od = target_opacity * (1 - opacity);
	return {
		delay,
		duration,
		easing,
		css: (_t, u) => `
			transform: ${transform} scale(${1 - sd * u});
			opacity: ${target_opacity - od * u}
		`
	};
}

PanelContainer[FILENAME] = 'ui/components/PanelContainer.svelte';

var root$r = add_locations(from_html(`<div><!></div>`), PanelContainer[FILENAME], [[12, 0]]);

function PanelContainer($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, PanelContainer);

	/**
	 * @typedef {Object} Props
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 */
	/** @type {Props} */
	let classes = prop($$props, 'class', 3, "");

	var $$exports = { ...legacy_api() };
	var div = root$r();
	var node = child(div);

	add_svelte_meta(() => snippet(node, () => $$props.children ?? noop), 'render', PanelContainer, 13, 1);
	template_effect(() => set_class(div, 1, `panel-container ${classes() ?? ''}`));
	append($$anchor, div);

	return pop($$exports);
}

PanelRow[FILENAME] = 'ui/components/PanelRow.svelte';

var root_1$o = add_locations(from_html(`<div class="gradient svelte-q90jdq"></div>`), PanelRow[FILENAME], [[23, 2]]);
var root$q = add_locations(from_html(`<div><!> <!></div>`), PanelRow[FILENAME], [[21, 0]]);

function PanelRow($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, PanelRow);

	/**
	 * @typedef {Object} Props
	 * @property {boolean} [header]
	 * @property {boolean} [footer]
	 * @property {boolean} [gradient]
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 */
	/** @type {Props} */
	let header = prop($$props, 'header', 3, false),
		footer = prop($$props, 'footer', 3, false),
		gradient = prop($$props, 'gradient', 3, false),
		classes = prop($$props, 'class', 3, "");

	var $$exports = { ...legacy_api() };
	var div = root$q();
	let classes_1;
	var node = child(div);

	{
		var consequent = ($$anchor) => {
			var div_1 = root_1$o();

			append($$anchor, div_1);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (gradient()) $$render(consequent);
			}),
			'if',
			PanelRow,
			22,
			1
		);
	}

	var node_1 = sibling(node, 2);

	add_svelte_meta(() => snippet(node_1, () => $$props.children ?? noop), 'render', PanelRow, 25, 1);
	template_effect(() => classes_1 = set_class(div, 1, `panel-row ${classes() ?? ''}`, 'svelte-q90jdq', classes_1, { header: header(), footer: footer() }));
	append($$anchor, div);

	return pop($$exports);
}

DefinedInWPConfig[FILENAME] = 'ui/components/DefinedInWPConfig.svelte';

var root_1$n = add_locations(from_html(`<p class="wp-config"> </p>`), DefinedInWPConfig[FILENAME], [[14, 1]]);

function DefinedInWPConfig($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, DefinedInWPConfig);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {boolean} [defined]
	 */
	/** @type {Props} */
	let defined = prop($$props, 'defined', 3, false);

	var $$exports = { ...legacy_api() };
	var fragment = comment();
	var node = first_child(fragment);

	{
		var consequent = ($$anchor) => {
			var p = root_1$n();
			var text = child(p);
			template_effect(() => set_text(text, $strings().defined_in_wp_config));
			append($$anchor, p);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (defined()) $$render(consequent);
			}),
			'if',
			DefinedInWPConfig,
			13,
			0
		);
	}

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

ToggleSwitch[FILENAME] = 'ui/components/ToggleSwitch.svelte';

var root$p = add_locations(from_html(`<div><input type="checkbox"/> <label class="toggle-label"><!></label></div>`), ToggleSwitch[FILENAME], [[19, 0, [[20, 1], [26, 1]]]]);

function ToggleSwitch($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, ToggleSwitch);

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {boolean} [checked]
	 * @property {boolean} [disabled]
	 * @property {import("svelte").Snippet} [children]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, ""),
		checked = prop($$props, 'checked', 15, false),
		disabled = prop($$props, 'disabled', 3, false);

	var $$exports = { ...legacy_api() };
	var div = root$p();
	let classes;
	var input = child(div);

	var label = sibling(input, 2);
	var node = child(label);

	add_svelte_meta(() => snippet(node, () => $$props.children ?? noop), 'render', ToggleSwitch, 27, 2);

	template_effect(() => {
		classes = set_class(div, 1, 'toggle-switch', null, classes, { locked: disabled() });
		set_attribute(input, 'id', name());
		input.disabled = disabled();
		set_attribute(label, 'for', name());
	});

	bind_checked(
		input,
		function get() {
			return checked();
		},
		function set($$value) {
			checked($$value);
		}
	);

	append($$anchor, div);

	return pop($$exports);
}

HelpButton[FILENAME] = 'ui/components/HelpButton.svelte';

var root_1$m = add_locations(from_html(`<a class="help" target="_blank"><img class="icon help"/></a>`), HelpButton[FILENAME], [[25, 1, [[26, 2]]]]);

function HelpButton($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, HelpButton);

	const $docs = () => (
		validate_store(docs, 'docs'),
		store_get(docs, '$docs', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [key]
	 * @property {any} [url]
	 * @property {string} [desc]
	 */
	/** @type {Props} */
	let key = prop($$props, 'key', 3, ""),
		url = prop($$props, 'url', 19, () => key() && $docs().hasOwnProperty(key()) && $docs()[key()].hasOwnProperty("url") ? $docs()[key()].url : ""),
		desc = prop($$props, 'desc', 3, "");

	// If desc supplied, use it, otherwise try and get via docs store or fall back to default help description.
	let docs_desc = tag(user_derived(() => key() && $docs().hasOwnProperty(key()) && $docs()[key()].hasOwnProperty("desc") ? $docs()[key()].desc : $strings().help_desc), 'docs_desc');

	let alt = tag(user_derived(() => desc().length ? desc() : get(docs_desc)), 'alt');
	let title = tag(user_derived(() => get(alt)), 'title');
	var $$exports = { ...legacy_api() };
	var fragment = comment();
	var node = first_child(fragment);

	{
		var consequent = ($$anchor) => {
			var a = root_1$m();
			var img = child(a);

			template_effect(() => {
				set_attribute(a, 'href', url());
				set_attribute(a, 'title', get(title));
				set_attribute(a, 'data-setting-key', key());
				set_attribute(img, 'src', $urls().assets + 'img/icon/help.svg');
				set_attribute(img, 'alt', get(alt));
			});

			append($$anchor, a);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (url()) $$render(consequent);
			}),
			'if',
			HelpButton,
			24,
			0
		);
	}

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

Panel[FILENAME] = 'ui/components/Panel.svelte';

var root_1$l = add_locations(from_html(`<div class="heading"><h2> </h2> <!> <!></div>`), Panel[FILENAME], [[130, 2, [[131, 3]]]]);
var root_7$3 = add_locations(from_html(`<!>  <h3> </h3>`, 1), Panel[FILENAME], [[150, 5]]);
var root_9$3 = add_locations(from_html(`<h3> </h3>`), Panel[FILENAME], [[152, 5]]);
var root_12$3 = add_locations(from_html(`<div class="provider"><a href="/storage/provider" class="link"><img/> </a></div>`), Panel[FILENAME], [[159, 5, [[160, 6, [[161, 7]]]]]]);
var root_6$4 = add_locations(from_html(`<!> <!> <!> <!> <!>`, 1), Panel[FILENAME], []);
var root_4$5 = add_locations(from_html(`<!> <!>`, 1), Panel[FILENAME], []);
var root$o = add_locations(from_html(`<div><!> <!></div>`), Panel[FILENAME], [[115, 0]]);

function Panel($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Panel);

	var $$ownership_validator = create_ownership_validator($$props);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $settingsLocked = () => (
		validate_store(get(settingsLocked), 'settingsLocked'),
		store_get(get(settingsLocked), '$settingsLocked', $$stores)
	);

	const $defined_settings = () => (
		validate_store(defined_settings, 'defined_settings'),
		store_get(defined_settings, '$defined_settings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {any} [ref]
	 * @property {string} [name]
	 * @property {string} [heading]
	 * @property {boolean} [defined]
	 * @property {boolean} [multi]
	 * @property {boolean} [flyout]
	 * @property {string} [toggleName]
	 * @property {boolean} [toggle]
	 * @property {boolean} [refresh]
	 * @property {any} [refreshText]
	 * @property {any} [refreshDesc]
	 * @property {boolean} [refreshing]
	 * @property {string} [helpKey]
	 * @property {string} [helpURL]
	 * @property {any} [helpDesc]
	 * @property {any} [storageProvider]
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 * @property {function} [onclick]
	 * @property {function} [onfocusout]
	 * @property {function} [onmouseenter]
	 * @property {function} [onmouseleave]
	 * @property {function} [onmousedown]
	 * @property {function} [onCancel]
	 * @property {function} [onRefresh]
	 */
	/** @type {Props} */
	let ref = prop($$props, 'ref', 31, () => tag_proxy(proxy({}), 'ref')),
		name = prop($$props, 'name', 3, ""),
		heading = prop($$props, 'heading', 3, ""),
		defined = prop($$props, 'defined', 3, false),
		multi = prop($$props, 'multi', 3, false),
		flyout = prop($$props, 'flyout', 3, false),
		toggleName = prop($$props, 'toggleName', 3, ""),
		toggle = prop($$props, 'toggle', 15, false),
		refresh = prop($$props, 'refresh', 3, false),
		refreshText = prop($$props, 'refreshText', 19, () => $strings().refresh_title),
		refreshDesc = prop($$props, 'refreshDesc', 19, refreshText),
		refreshing = prop($$props, 'refreshing', 3, false),
		helpKey = prop($$props, 'helpKey', 3, ""),
		helpURL = prop($$props, 'helpURL', 3, ""),
		helpDesc = prop($$props, 'helpDesc', 19, () => $strings().help_desc),
		storageProvider = prop($$props, 'storageProvider', 3, null),
		classes = prop($$props, 'class', 3, ""),
		onCancel = prop($$props, 'onCancel', 19, () => ({})),
		onRefresh = prop($$props, 'onRefresh', 19, () => ({}));

	// Parent page may want to be locked.
	let settingsLocked = tag(state(proxy(writable(false))), 'settingsLocked');

	if (hasContext("settingsLocked")) {
		store_unsub(set(settingsLocked, getContext("settingsLocked"), true), '$settingsLocked', $$stores);
	}

	let locked = tag(user_derived($settingsLocked), 'locked');
	let toggleDisabled = tag(user_derived(() => $defined_settings().includes(toggleName()) || get(locked)), 'toggleDisabled');

	/**
	 * If appropriate, clicking the header toggles to toggle switch.
	 */
	function headingClickHandler() {
		if (toggleName() && !get(toggleDisabled)) {
			toggle(!toggle());
		}
	}

	/**
	 * Catch escape key and emit a custom cancel event.
	 *
	 * @param {KeyboardEvent} event
	 */
	function handleKeyup(event) {
		if (strict_equals(event.key, "Escape") && strict_equals(typeof onCancel(), "function")) {
			event.preventDefault();
			onCancel()();
		}
	}

	/**
	 * Handle refresh request, uses onRefresh callback if set.
	 */
	function handleRefresh() {
		if (strict_equals(typeof onRefresh(), "function")) {
			onRefresh()();
		}
	}

	var $$exports = { ...legacy_api() };
	var div = root$o();
	let classes_1;
	var node = child(div);

	{
		var consequent_2 = ($$anchor) => {
			var div_1 = root_1$l();
			var h2 = child(div_1);
			var text = child(h2);

			var node_1 = sibling(h2, 2);

			{
				var consequent = ($$anchor) => {
					add_svelte_meta(
						() => HelpButton($$anchor, {
							get url() {
								return helpURL();
							},

							get desc() {
								return helpDesc();
							}
						}),
						'component',
						Panel,
						133,
						4,
						{ componentTag: 'HelpButton' }
					);
				};

				var consequent_1 = ($$anchor) => {
					add_svelte_meta(
						() => HelpButton($$anchor, {
							get key() {
								return helpKey();
							},

							get desc() {
								return helpDesc();
							}
						}),
						'component',
						Panel,
						135,
						4,
						{ componentTag: 'HelpButton' }
					);
				};

				add_svelte_meta(
					() => if_block(node_1, ($$render) => {
						if (helpURL()) $$render(consequent); else if (helpKey()) $$render(consequent_1, 1);
					}),
					'if',
					Panel,
					132,
					3
				);
			}

			var node_2 = sibling(node_1, 2);

			add_svelte_meta(
				() => DefinedInWPConfig(node_2, {
					get defined() {
						return defined();
					}
				}),
				'component',
				Panel,
				137,
				3,
				{ componentTag: 'DefinedInWPConfig' }
			);
			template_effect(() => set_text(text, heading()));
			append($$anchor, div_1);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (!multi() && heading()) $$render(consequent_2);
			}),
			'if',
			Panel,
			129,
			1
		);
	}

	var node_3 = sibling(node, 2);

	add_svelte_meta(
		() => PanelContainer(node_3, {
			get class() {
				return classes();
			},

			children: wrap_snippet(Panel, ($$anchor, $$slotProps) => {
				var fragment_2 = root_4$5();
				var node_4 = first_child(fragment_2);

				{
					var consequent_8 = ($$anchor) => {
						add_svelte_meta(
							() => PanelRow($$anchor, {
								header: true,
								children: wrap_snippet(Panel, ($$anchor, $$slotProps) => {
									var fragment_4 = root_6$4();
									var node_5 = first_child(fragment_4);

									{
										var consequent_3 = ($$anchor) => {
											var fragment_5 = root_7$3();
											var node_6 = first_child(fragment_5);

											{
												$$ownership_validator.binding('toggle', ToggleSwitch, toggle);

												add_svelte_meta(
													() => ToggleSwitch(node_6, {
														get name() {
															return toggleName();
														},

														get disabled() {
															return get(toggleDisabled);
														},

														get checked() {
															return toggle();
														},

														set checked($$value) {
															toggle($$value);
														},

														children: wrap_snippet(Panel, ($$anchor, $$slotProps) => {
															next();

															var text_1 = text();

															template_effect(() => set_text(text_1, heading()));
															append($$anchor, text_1);
														}),
														$$slots: { default: true }
													}),
													'component',
													Panel,
													144,
													5,
													{ componentTag: 'ToggleSwitch' }
												);
											}

											var h3 = sibling(node_6, 2);
											let classes_2;
											var text_2 = child(h3, true);

											reset(h3);

											template_effect(() => {
												classes_2 = set_class(h3, 1, 'toggler svelte-1sd9f5y', null, classes_2, { toggleDisabled: get(toggleDisabled) });
												set_text(text_2, heading());
											});

											delegated('click', h3, headingClickHandler);
											append($$anchor, fragment_5);
										};

										var alternate = ($$anchor) => {
											var h3_1 = root_9$3();
											var text_3 = child(h3_1, true);

											reset(h3_1);
											template_effect(() => set_text(text_3, heading()));
											append($$anchor, h3_1);
										};

										add_svelte_meta(
											() => if_block(node_5, ($$render) => {
												if (toggleName()) $$render(consequent_3); else $$render(alternate, -1);
											}),
											'if',
											Panel,
											143,
											4
										);
									}

									var node_7 = sibling(node_5, 2);

									add_svelte_meta(
										() => DefinedInWPConfig(node_7, {
											get defined() {
												return defined();
											}
										}),
										'component',
										Panel,
										154,
										4,
										{ componentTag: 'DefinedInWPConfig' }
									);

									var node_8 = sibling(node_7, 2);

									{
										var consequent_4 = ($$anchor) => {
											add_svelte_meta(
												() => Button($$anchor, {
													refresh: true,
													get refreshing() {
														return refreshing();
													},

													get title() {
														return refreshDesc();
													},
													onclick: handleRefresh,
													children: wrap_snippet(Panel, ($$anchor, $$slotProps) => {
														var fragment_8 = comment();
														var node_9 = first_child(fragment_8);

														html(node_9, refreshText);
														append($$anchor, fragment_8);
													}),
													$$slots: { default: true }
												}),
												'component',
												Panel,
												156,
												5,
												{ componentTag: 'Button' }
											);
										};

										add_svelte_meta(
											() => if_block(node_8, ($$render) => {
												if (refresh()) $$render(consequent_4);
											}),
											'if',
											Panel,
											155,
											4
										);
									}

									var node_10 = sibling(node_8, 2);

									{
										var consequent_5 = ($$anchor) => {
											var div_2 = root_12$3();
											var a = child(div_2);
											var img = child(a);
											var text_4 = sibling(img);

											reset(a);
											action(a, ($$node) => link?.($$node));
											reset(div_2);

											template_effect(() => {
												set_attribute(img, 'src', storageProvider().link_icon);
												set_attribute(img, 'alt', storageProvider().icon_desc);
												set_text(text_4, ` ${storageProvider().provider_service_name ?? ''}`);
											});

											append($$anchor, div_2);
										};

										add_svelte_meta(
											() => if_block(node_10, ($$render) => {
												if (storageProvider()) $$render(consequent_5);
											}),
											'if',
											Panel,
											158,
											4
										);
									}

									var node_11 = sibling(node_10, 2);

									{
										var consequent_6 = ($$anchor) => {
											add_svelte_meta(
												() => HelpButton($$anchor, {
													get url() {
														return helpURL();
													},

													get desc() {
														return helpDesc();
													}
												}),
												'component',
												Panel,
												167,
												5,
												{ componentTag: 'HelpButton' }
											);
										};

										var consequent_7 = ($$anchor) => {
											add_svelte_meta(
												() => HelpButton($$anchor, {
													get key() {
														return helpKey();
													},

													get desc() {
														return helpDesc();
													}
												}),
												'component',
												Panel,
												169,
												5,
												{ componentTag: 'HelpButton' }
											);
										};

										add_svelte_meta(
											() => if_block(node_11, ($$render) => {
												if (helpURL()) $$render(consequent_6); else if (helpKey()) $$render(consequent_7, 1);
											}),
											'if',
											Panel,
											166,
											4
										);
									}

									append($$anchor, fragment_4);
								}),
								$$slots: { default: true }
							}),
							'component',
							Panel,
							142,
							3,
							{ componentTag: 'PanelRow' }
						);
					};

					add_svelte_meta(
						() => if_block(node_4, ($$render) => {
							if (multi() && heading()) $$render(consequent_8);
						}),
						'if',
						Panel,
						141,
						2
					);
				}

				var node_12 = sibling(node_4, 2);

				add_svelte_meta(() => snippet(node_12, () => $$props.children ?? noop), 'render', Panel, 174, 2);
				append($$anchor, fragment_2);
			}),
			$$slots: { default: true }
		}),
		'component',
		Panel,
		140,
		1,
		{ componentTag: 'PanelContainer' }
	);
	bind_this(div, ($$value) => ref($$value), () => ref());
	template_effect(() => classes_1 = set_class(div, 1, `panel ${name() ?? ''}`, 'svelte-1sd9f5y', classes_1, { multi: multi(), flyout: flyout(), locked: get(locked) }));

	delegated('focusout', div, function (...$$args) {
		apply(() => $$props.onfocusout, this, $$args, Panel, [122, 2]);
	});

	event('mouseenter', div, function (...$$args) {
		apply(() => $$props.onmouseenter, this, $$args, Panel, [123, 2]);
	});

	event('mouseleave', div, function (...$$args) {
		apply(() => $$props.onmouseleave, this, $$args, Panel, [124, 2]);
	});

	delegated('mousedown', div, function (...$$args) {
		apply(() => $$props.onmousedown, this, $$args, Panel, [125, 2]);
	});

	delegated('click', div, function (...$$args) {
		apply(() => $$props.onclick, this, $$args, Panel, [126, 2]);
	});

	delegated('keyup', div, handleKeyup);
	transition(3, div, () => fade, () => ({ duration: flyout() ? 200 : 0 }));
	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

delegate(['focusout', 'mousedown', 'click', 'keyup']);

StorageSettingsHeadingRow[FILENAME] = 'ui/components/StorageSettingsHeadingRow.svelte';

var root_1$k = add_locations(from_html(`<img class="svelte-oic18e"/> <div class="provider-details svelte-oic18e"><h3 class="svelte-oic18e"> </h3> <p class="console-details svelte-oic18e"><a class="console svelte-oic18e" target="_blank"> </a> <span class="region svelte-oic18e"> </span></p></div> <!>`, 1), StorageSettingsHeadingRow[FILENAME], [[24, 1], [25, 1, [[26, 2], [27, 2, [[28, 3], [29, 3]]]]]]);

function StorageSettingsHeadingRow($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, StorageSettingsHeadingRow);

	const $storage_provider = () => (
		validate_store(storage_provider, 'storage_provider'),
		store_get(storage_provider, '$storage_provider', $$stores)
	);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const $region_name = () => (
		validate_store(region_name, 'region_name'),
		store_get(region_name, '$region_name', $$stores)
	);

	const $settingsLocked = () => (
		validate_store(get(settingsLocked), 'settingsLocked'),
		store_get(get(settingsLocked), '$settingsLocked', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	// Parent page may want to be locked.
	let settingsLocked = tag(state(proxy(writable(false))), 'settingsLocked');

	if (hasContext("settingsLocked")) {
		store_unsub(set(settingsLocked, getContext("settingsLocked"), true), '$settingsLocked', $$stores);
	}

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => PanelRow($$anchor, {
			header: true,
			gradient: true,
			get class() {
				return `storage ${$storage_provider().provider_key_name ?? ''}`;
			},

			children: wrap_snippet(StorageSettingsHeadingRow, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$k();
				var img = first_child(fragment_1);
				var div = sibling(img, 2);
				var h3 = child(div);
				var text$1 = child(h3, true);

				reset(h3);

				var p = sibling(h3, 2);
				var a = child(p);
				var text_1 = child(a, true);

				reset(a);

				var span = sibling(a, 2);
				var text_2 = child(span, true);

				reset(span);
				reset(p);
				reset(div);

				var node = sibling(div, 2);

				add_svelte_meta(
					() => Button(node, {
						outline: true,
						onclick: () => push('/storage/provider'),
						get title() {
							return $strings().edit_storage_provider;
						},

						get disabled() {
							return $settingsLocked();
						},

						children: wrap_snippet(StorageSettingsHeadingRow, ($$anchor, $$slotProps) => {
							next();

							var text_3 = text();

							template_effect(() => set_text(text_3, $strings().edit));
							append($$anchor, text_3);
						}),
						$$slots: { default: true }
					}),
					'component',
					StorageSettingsHeadingRow,
					32,
					1,
					{ componentTag: 'Button' }
				);

				template_effect(() => {
					set_attribute(img, 'src', $storage_provider().icon);
					set_attribute(img, 'alt', $storage_provider().provider_service_name);
					set_text(text$1, $storage_provider().provider_service_name);
					set_attribute(a, 'href', $urls().storage_provider_console_url);
					set_attribute(a, 'title', $strings().view_provider_console);
					set_text(text_1, $settings().bucket);
					set_attribute(span, 'title', $settings().region);
					set_text(text_2, $region_name());
				});

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		StorageSettingsHeadingRow,
		23,
		0,
		{ componentTag: 'PanelRow' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

/**
 * Return a promise that resolves after a minimum amount of time has elapsed.
 *
 * @param {number} start   Timestamp of when the action started.
 * @param {number} minTime Minimum amount of time to delay in milliseconds.
 *
 * @return {Promise}
 */
function delayMin( start, minTime ) {
	let elapsed = Date.now() - start;
	return new Promise( ( resolve ) => setTimeout( resolve, minTime - elapsed ) );
}

CheckAgain[FILENAME] = 'ui/components/CheckAgain.svelte';

var root$n = add_locations(from_html(`<div class="check-again svelte-qs7gng"><!> <span class="last-update"> </span></div>`), CheckAgain[FILENAME], [[42, 0, [[52, 1]]]]);

function CheckAgain($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, CheckAgain);

	const $revalidatingSettings = () => (
		validate_store(revalidatingSettings, 'revalidatingSettings'),
		store_get(revalidatingSettings, '$revalidatingSettings', $$stores)
	);

	const $settings_validation = () => (
		validate_store(settings_validation, 'settings_validation'),
		store_get(settings_validation, '$settings_validation', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [section]
	 */
	/** @type {Props} */
	let section = prop($$props, 'section', 3, "");

	let refreshing = tag(user_derived($revalidatingSettings), 'refreshing');
	let datetime = tag(user_derived(() => new Date($settings_validation()[section()].timestamp * 1000).toString()), 'datetime');

	/**
	 * Calls the API to revalidate settings.
	 */
	async function revalidate() {
		let start = Date.now();
		let params = { revalidateSettings: true, section: section() };

		set(refreshing, true);

		let json = (await track_reactivity_loss(api.get("state", params)))();

		(await track_reactivity_loss(delayMin(start, 1000)))();
		appState.updateState(json);
		set(refreshing, false);
	}

	var $$exports = { ...legacy_api() };
	var div = root$n();
	var node = child(div);

	{
		var consequent = ($$anchor) => {
			add_svelte_meta(
				() => Button($$anchor, {
					refresh: true,
					get refreshing() {
						return get(refreshing);
					},

					get title() {
						return $strings().check_again_desc;
					},
					onclick: revalidate,
					children: wrap_snippet(CheckAgain, ($$anchor, $$slotProps) => {
						next();

						var text$1 = text();

						template_effect(() => set_text(text$1, $strings().check_again_title));
						append($$anchor, text$1);
					}),
					$$slots: { default: true }
				}),
				'component',
				CheckAgain,
				44,
				2,
				{ componentTag: 'Button' }
			);
		};

		var alternate = ($$anchor) => {
			add_svelte_meta(
				() => Button($$anchor, {
					refresh: true,
					get refreshing() {
						return get(refreshing);
					},

					get title() {
						return $strings().check_again_desc;
					},

					children: wrap_snippet(CheckAgain, ($$anchor, $$slotProps) => {
						next();

						var text_1 = text();

						template_effect(() => set_text(text_1, $strings().check_again_active));
						append($$anchor, text_1);
					}),
					$$slots: { default: true }
				}),
				'component',
				CheckAgain,
				48,
				2,
				{ componentTag: 'Button' }
			);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (!get(refreshing)) $$render(consequent); else $$render(alternate, -1);
			}),
			'if',
			CheckAgain,
			43,
			1
		);
	}

	var span = sibling(node, 2);
	var text_2 = child(span);

	template_effect(() => {
		set_attribute(span, 'title', get(datetime));
		set_text(text_2, $settings_validation()[section()].last_update);
	});

	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

SettingsValidationStatusRow[FILENAME] = 'ui/components/SettingsValidationStatusRow.svelte';

var root$m = add_locations(from_html(`<div><div class="content in-panel"><div class="icon type in-panel"><img class="icon type"/></div> <div class="body"></div> <!></div></div>`), SettingsValidationStatusRow[FILENAME], [[26, 0, [[33, 1, [[34, 2, [[35, 3]]], [38, 2]]]]]]);

function SettingsValidationStatusRow($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, SettingsValidationStatusRow);

	const $settings_validation = () => (
		validate_store(settings_validation, 'settings_validation'),
		store_get(settings_validation, '$settings_validation', $$stores)
	);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [section]
	 */
	/** @type {Props} */
	let section = prop($$props, 'section', 3, "");

	let success = tag(user_derived(() => strict_equals($settings_validation()[section()].type, "success")), 'success');
	let warning = tag(user_derived(() => strict_equals($settings_validation()[section()].type, "warning")), 'warning');
	let error = tag(user_derived(() => strict_equals($settings_validation()[section()].type, "error")), 'error');
	let info = tag(user_derived(() => strict_equals($settings_validation()[section()].type, "info")), 'info');
	let type = tag(user_derived(() => $settings_validation()[section()].type), 'type');
	let message = tag(user_derived(() => "<p>" + $settings_validation()[section()].message + "</p>"), 'message');
	let iconURL = tag(user_derived(() => $urls().assets + "img/icon/notification-" + $settings_validation()[section()].type + ".svg"), 'iconURL');
	var $$exports = { ...legacy_api() };
	var div = root$m();
	let classes;
	var div_1 = child(div);
	var div_2 = child(div_1);
	var img = child(div_2);

	var div_3 = sibling(div_2, 2);

	html(div_3, () => get(message), true);

	var node = sibling(div_3, 2);

	add_svelte_meta(
		() => CheckAgain(node, {
			get section() {
				return section();
			}
		}),
		'component',
		SettingsValidationStatusRow,
		42,
		2,
		{ componentTag: 'CheckAgain' }
	);

	template_effect(() => {
		classes = set_class(div, 1, `notification in-panel multiline ${section() ?? ''}`, null, classes, {
			success: get(success),
			warning: get(warning),
			error: get(error),
			info: get(info)
		});

		set_attribute(img, 'src', get(iconURL));
		set_attribute(img, 'alt', `${get(type) ?? ''} icon`);
	});

	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

SettingNotifications[FILENAME] = 'ui/components/SettingNotifications.svelte';

var root_3$7 = add_locations(from_html(`<p></p>`), SettingNotifications[FILENAME], [[51, 3]]);

function SettingNotifications($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, SettingNotifications);

	const $settings_notifications = () => (
		validate_store(settings_notifications, 'settings_notifications'),
		store_get(settings_notifications, '$settings_notifications', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * Compares two notification objects to sort them into a preferred order.
	 *
	 * Order should be errors, then warnings and finally anything else alphabetically by type.
	 * As these (inline) notifications are typically displayed under a setting,
	 * this ensures the most important notifications are nearest the control.
	 *
	 * @param {Object} a
	 * @param {Object} b
	 *
	 * @return {number}
	 */
	function compareNotificationTypes(a, b) {
		// Sort errors to the top.
		if (strict_equals(a.type, "error") && strict_equals(b.type, "error", false)) {
			return -1;
		}

		if (strict_equals(b.type, "error") && strict_equals(a.type, "error", false)) {
			return 1;
		}

		// Next sort warnings.
		if (strict_equals(a.type, "warning") && strict_equals(b.type, "warning", false)) {
			return -1;
		}

		if (strict_equals(b.type, "warning") && strict_equals(a.type, "warning", false)) {
			return 1;
		}

		// Anything else, just sort by type for stability.
		if (a.type < b.type) {
			return -1;
		}

		if (b.type < a.type) {
			return 1;
		}

		return 0;
	}

	var $$exports = { ...legacy_api() };
	var fragment = comment();
	var node = first_child(fragment);

	{
		var consequent = ($$anchor) => {
			var fragment_1 = comment();
			var node_1 = first_child(fragment_1);

			add_svelte_meta(
				() => each(
					node_1,
					1,
					() => [
						...$settings_notifications().get($$props.settingKey).values()
					].sort(compareNotificationTypes),
					(notification) => notification,
					($$anchor, notification) => {
						add_svelte_meta(
							() => Notification($$anchor, {
								get notification() {
									return get(notification);
								},

								children: wrap_snippet(SettingNotifications, ($$anchor, $$slotProps) => {
									var p = root_3$7();

									html(p, () => get(notification).message, true);
									reset(p);
									append($$anchor, p);
								}),
								$$slots: { default: true }
							}),
							'component',
							SettingNotifications,
							50,
							2,
							{ componentTag: 'Notification' }
						);
					}
				),
				'each',
				SettingNotifications,
				49,
				1
			);

			append($$anchor, fragment_1);
		};

		var d = user_derived(() => $settings_notifications().has($$props.settingKey));

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (get(d)) $$render(consequent);
			}),
			'if',
			SettingNotifications,
			48,
			0
		);
	}

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

SettingsPanelOption[FILENAME] = 'ui/components/SettingsPanelOption.svelte';

var root_2$a = add_locations(from_html(`<!>  <h4> </h4>`, 1), SettingsPanelOption[FILENAME], [[119, 3]]);
var root_4$4 = add_locations(from_html(`<h4> </h4>`), SettingsPanelOption[FILENAME], [[121, 3]]);
var root_1$j = add_locations(from_html(`<!> <!>`, 1), SettingsPanelOption[FILENAME], []);
var root_5$6 = add_locations(from_html(`<p></p>`), SettingsPanelOption[FILENAME], [[126, 2]]);
var root_7$2 = add_locations(from_html(`<input type="text" minlength="1" size="10"/> <label> </label>`, 1), SettingsPanelOption[FILENAME], [[130, 3], [143, 3]]);
var root_8$4 = add_locations(from_html(`<p class="input-error"> </p>`), SettingsPanelOption[FILENAME], [[148, 3]]);
var root_6$3 = add_locations(from_html(`<!> <!>`, 1), SettingsPanelOption[FILENAME], []);
var root$l = add_locations(from_html(`<div><!> <!> <!> <!> <!> <!></div>`), SettingsPanelOption[FILENAME], [[110, 0]]);

function SettingsPanelOption($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, SettingsPanelOption);

	var $$ownership_validator = create_ownership_validator($$props);

	const $settingsLocked = () => (
		validate_store(get(settingsLocked), 'settingsLocked'),
		store_get(get(settingsLocked), '$settingsLocked', $$stores)
	);

	const $definedSettings = () => (
		validate_store(definedSettings(), 'definedSettings'),
		store_get(definedSettings(), '$definedSettings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [heading]
	 * @property {string} [description]
	 * @property {string} [placeholder]
	 * @property {boolean} [nested]
	 * @property {boolean} [first]
	 * @property {string} [toggleName]
	 * @property {boolean} [toggle]
	 * @property {string} [textName]
	 * @property {string} [text]
	 * @property {boolean} [alwaysShowText]
	 * @property {any} [definedSettings]
	 * @property {any} [validator] - Optional validator function.
	 * @property {import("svelte").Snippet} [children]
	 */
	/** @type {Props} */
	let heading = prop($$props, 'heading', 3, ""),
		description = prop($$props, 'description', 3, ""),
		placeholder = prop($$props, 'placeholder', 3, ""),
		nested = prop($$props, 'nested', 3, false),
		first = prop($$props, 'first', 3, false),
		toggleName = prop($$props, 'toggleName', 3, ""),
		toggle = prop($$props, 'toggle', 15, false),
		textName = prop($$props, 'textName', 3, ""),
		text$1 = prop($$props, 'text', 15, ""),
		alwaysShowText = prop($$props, 'alwaysShowText', 3, false),
		definedSettings = prop($$props, 'definedSettings', 3, defined_settings),
		validator = prop($$props, 'validator', 3, (textValue) => "");

	// Parent page may want to be locked.
	let settingsLocked = tag(state(proxy(writable(false))), 'settingsLocked');

	let textDirty = tag(state(false), 'textDirty');

	if (hasContext("settingsLocked")) {
		store_unsub(set(settingsLocked, getContext("settingsLocked"), true), '$settingsLocked', $$stores);
	}

	let locked = tag(user_derived($settingsLocked), 'locked');
	let toggleDisabled = tag(user_derived(() => $definedSettings().includes(toggleName()) || get(locked)), 'toggleDisabled');
	let textDisabled = tag(user_derived(() => $definedSettings().includes(textName()) || get(locked)), 'textDisabled');
	let input = tag(user_derived(() => (toggleName() && toggle() || !toggleName() || alwaysShowText()) && textName()), 'input');
	let headingName = tag(user_derived(() => get(input) ? textName() + "-heading" : toggleName()), 'headingName');

	/**
	 * Validate the text if validator function supplied.
	 *
	 * @param {string} text
	 * @param {bool} toggle
	 *
	 * @return {string}
	 */
	function validateText(text, toggle) {
		let message = "";

		if (strict_equals(validator(), undefined, false) && toggle && !get(textDisabled)) {
			message = validator()(text);
		}

		return message;
	}

	function onTextInput() {
		set(textDirty, true);
	}

	let validationError = tag(user_derived(() => validateText(text$1(), toggle())), 'validationError');

	/**
	 * Keep validationErrors store up to date as validationError changes.
	 */
	user_effect(() => {
		validationErrors.update((_validationErrors) => {
			if (_validationErrors.has(textName()) && strict_equals(get(validationError), "")) {
				_validationErrors.delete(textName());
			} else if (strict_equals(get(validationError), "", false)) {
				_validationErrors.set(textName(), get(validationError));
			}

			return _validationErrors;
		});
	});

	/**
	 * If appropriate, clicking the header toggles to toggle switch.
	 */
	function headingClickHandler() {
		if (toggleName() && !get(toggleDisabled)) {
			toggle(!toggle());
		}
	}

	var $$exports = { ...legacy_api() };
	var div = root$l();
	let classes;
	var node = child(div);

	add_svelte_meta(
		() => PanelRow(node, {
			class: 'option',
			children: wrap_snippet(SettingsPanelOption, ($$anchor, $$slotProps) => {
				var fragment = root_1$j();
				var node_1 = first_child(fragment);

				{
					var consequent = ($$anchor) => {
						var fragment_1 = root_2$a();
						var node_2 = first_child(fragment_1);

						{
							$$ownership_validator.binding('toggle', ToggleSwitch, toggle);

							add_svelte_meta(
								() => ToggleSwitch(node_2, {
									get name() {
										return toggleName();
									},

									get disabled() {
										return get(toggleDisabled);
									},

									get checked() {
										return toggle();
									},

									set checked($$value) {
										toggle($$value);
									},

									children: wrap_snippet(SettingsPanelOption, ($$anchor, $$slotProps) => {
										next();

										var text_1 = text();

										template_effect(() => set_text(text_1, heading()));
										append($$anchor, text_1);
									}),
									$$slots: { default: true }
								}),
								'component',
								SettingsPanelOption,
								113,
								3,
								{ componentTag: 'ToggleSwitch' }
							);
						}

						var h4 = sibling(node_2, 2);
						let classes_1;
						var text_2 = child(h4, true);

						reset(h4);

						template_effect(() => {
							set_attribute(h4, 'id', get(headingName));
							classes_1 = set_class(h4, 1, 'toggler svelte-oeg51s', null, classes_1, { toggleDisabled: get(toggleDisabled) });
							set_text(text_2, heading());
						});

						delegated('click', h4, headingClickHandler);
						append($$anchor, fragment_1);
					};

					var alternate = ($$anchor) => {
						var h4_1 = root_4$4();
						var text_3 = child(h4_1, true);

						reset(h4_1);

						template_effect(() => {
							set_attribute(h4_1, 'id', get(headingName));
							set_text(text_3, heading());
						});

						append($$anchor, h4_1);
					};

					add_svelte_meta(
						() => if_block(node_1, ($$render) => {
							if (toggleName()) $$render(consequent); else $$render(alternate, -1);
						}),
						'if',
						SettingsPanelOption,
						112,
						2
					);
				}

				var node_3 = sibling(node_1, 2);

				{
					let $0 = user_derived(() => $definedSettings().includes(toggleName()) || get(input) && $definedSettings().includes(textName()));

					add_svelte_meta(
						() => DefinedInWPConfig(node_3, {
							get defined() {
								return get($0);
							}
						}),
						'component',
						SettingsPanelOption,
						123,
						2,
						{ componentTag: 'DefinedInWPConfig' }
					);
				}

				append($$anchor, fragment);
			}),
			$$slots: { default: true }
		}),
		'component',
		SettingsPanelOption,
		111,
		1,
		{ componentTag: 'PanelRow' }
	);

	var node_4 = sibling(node, 2);

	add_svelte_meta(
		() => PanelRow(node_4, {
			class: 'desc',
			children: wrap_snippet(SettingsPanelOption, ($$anchor, $$slotProps) => {
				var p = root_5$6();

				html(p, description, true);
				reset(p);
				append($$anchor, p);
			}),
			$$slots: { default: true }
		}),
		'component',
		SettingsPanelOption,
		125,
		1,
		{ componentTag: 'PanelRow' }
	);

	var node_5 = sibling(node_4, 2);

	{
		var consequent_2 = ($$anchor) => {
			var fragment_3 = root_6$3();
			var node_6 = first_child(fragment_3);

			add_svelte_meta(
				() => PanelRow(node_6, {
					class: 'input',
					children: wrap_snippet(SettingsPanelOption, ($$anchor, $$slotProps) => {
						var fragment_4 = root_7$2();
						var input_1 = first_child(fragment_4);

						remove_input_defaults(input_1);

						let classes_2;
						var label = sibling(input_1, 2);
						var text_4 = child(label, true);

						reset(label);

						template_effect(() => {
							set_attribute(input_1, 'id', textName());
							set_attribute(input_1, 'name', textName());
							set_attribute(input_1, 'placeholder', placeholder());
							input_1.disabled = get(textDisabled);
							set_attribute(input_1, 'aria-labelledby', get(headingName));
							classes_2 = set_class(input_1, 1, '', null, classes_2, { disabled: get(textDisabled) });
							set_attribute(label, 'for', textName());
							set_text(text_4, heading());
						});

						delegated('input', input_1, onTextInput);

						bind_value(
							input_1,
							function get() {
								return text$1();
							},
							function set($$value) {
								text$1($$value);
							}
						);

						append($$anchor, fragment_4);
					}),
					$$slots: { default: true }
				}),
				'component',
				SettingsPanelOption,
				129,
				2,
				{ componentTag: 'PanelRow' }
			);

			var node_7 = sibling(node_6, 2);

			{
				var consequent_1 = ($$anchor) => {
					var p_1 = root_8$4();
					var text_5 = child(p_1);
					template_effect(() => set_text(text_5, get(validationError)));
					transition(3, p_1, () => slide);
					append($$anchor, p_1);
				};

				add_svelte_meta(
					() => if_block(node_7, ($$render) => {
						if (get(validationError) && get(textDirty)) $$render(consequent_1);
					}),
					'if',
					SettingsPanelOption,
					147,
					2
				);
			}

			append($$anchor, fragment_3);
		};

		add_svelte_meta(
			() => if_block(node_5, ($$render) => {
				if (get(input)) $$render(consequent_2);
			}),
			'if',
			SettingsPanelOption,
			128,
			1
		);
	}

	var node_8 = sibling(node_5, 2);

	{
		var consequent_3 = ($$anchor) => {
			add_svelte_meta(
				() => SettingNotifications($$anchor, {
					get settingKey() {
						return toggleName();
					}
				}),
				'component',
				SettingsPanelOption,
				153,
				2,
				{ componentTag: 'SettingNotifications' }
			);
		};

		add_svelte_meta(
			() => if_block(node_8, ($$render) => {
				if (toggleName()) $$render(consequent_3);
			}),
			'if',
			SettingsPanelOption,
			152,
			1
		);
	}

	var node_9 = sibling(node_8, 2);

	{
		var consequent_4 = ($$anchor) => {
			add_svelte_meta(
				() => SettingNotifications($$anchor, {
					get settingKey() {
						return textName();
					}
				}),
				'component',
				SettingsPanelOption,
				157,
				2,
				{ componentTag: 'SettingNotifications' }
			);
		};

		add_svelte_meta(
			() => if_block(node_9, ($$render) => {
				if (textName()) $$render(consequent_4);
			}),
			'if',
			SettingsPanelOption,
			156,
			1
		);
	}

	var node_10 = sibling(node_9, 2);

	add_svelte_meta(() => snippet(node_10, () => $$props.children ?? noop), 'render', SettingsPanelOption, 160, 1);
	template_effect(() => classes = set_class(div, 1, 'setting', null, classes, { nested: nested(), first: first() }));
	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

delegate(['click', 'input']);

StorageSettingsPanel[FILENAME] = 'ui/components/StorageSettingsPanel.svelte';

var root_1$i = add_locations(from_html(`<!> <!> <!> <!> <!> <!> <!>`, 1), StorageSettingsPanel[FILENAME], []);

function StorageSettingsPanel($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, StorageSettingsPanel);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();
	var $$exports = { ...legacy_api() };

	init();

	add_svelte_meta(
		() => Panel($$anchor, {
			name: 'settings',
			get heading() {
				return $strings().storage_settings_title;
			},
			helpKey: 'storage-provider',
			children: wrap_snippet(StorageSettingsPanel, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$i();
				var node = first_child(fragment_1);

				add_svelte_meta(() => StorageSettingsHeadingRow(node, {}), 'component', StorageSettingsPanel, 11, 1, { componentTag: 'StorageSettingsHeadingRow' });

				var node_1 = sibling(node, 2);

				add_svelte_meta(() => SettingsValidationStatusRow(node_1, { section: 'storage' }), 'component', StorageSettingsPanel, 12, 1, { componentTag: 'SettingsValidationStatusRow' });

				var node_2 = sibling(node_1, 2);

				add_svelte_meta(
					() => SettingsPanelOption(node_2, {
						get heading() {
							return $strings().copy_files_to_bucket;
						},

						get description() {
							return $strings().copy_files_to_bucket_desc;
						},
						toggleName: 'copy-to-s3',
						get toggle() {
							return $settings()["copy-to-s3"];
						},

						set toggle($$value) {
							store_mutate(settings, untrack($settings)["copy-to-s3"] = $$value, untrack($settings));
						},
						$$legacy: true
					}),
					'component',
					StorageSettingsPanel,
					13,
					1,
					{ componentTag: 'SettingsPanelOption' }
				);

				var node_3 = sibling(node_2, 2);

				add_svelte_meta(
					() => SettingsPanelOption(node_3, {
						get heading() {
							return $strings().remove_local_file;
						},

						get description() {
							return $strings().remove_local_file_desc;
						},
						toggleName: 'remove-local-file',
						get toggle() {
							return $settings()["remove-local-file"];
						},

						set toggle($$value) {
							store_mutate(settings, untrack($settings)["remove-local-file"] = $$value, untrack($settings));
						},
						$$legacy: true
					}),
					'component',
					StorageSettingsPanel,
					19,
					1,
					{ componentTag: 'SettingsPanelOption' }
				);

				var node_4 = sibling(node_3, 2);

				add_svelte_meta(
					() => SettingsPanelOption(node_4, {
						get heading() {
							return $strings().path;
						},

						get description() {
							return $strings().path_desc;
						},
						toggleName: 'enable-object-prefix',
						textName: 'object-prefix',
						get toggle() {
							return $settings()["enable-object-prefix"];
						},

						set toggle($$value) {
							store_mutate(settings, untrack($settings)["enable-object-prefix"] = $$value, untrack($settings));
						},

						get text() {
							return $settings()["object-prefix"];
						},

						set text($$value) {
							store_mutate(settings, untrack($settings)["object-prefix"] = $$value, untrack($settings));
						},
						$$legacy: true
					}),
					'component',
					StorageSettingsPanel,
					26,
					1,
					{ componentTag: 'SettingsPanelOption' }
				);

				var node_5 = sibling(node_4, 2);

				add_svelte_meta(
					() => SettingsPanelOption(node_5, {
						get heading() {
							return $strings().year_month;
						},

						get description() {
							return $strings().year_month_desc;
						},
						toggleName: 'use-yearmonth-folders',
						get toggle() {
							return $settings()["use-yearmonth-folders"];
						},

						set toggle($$value) {
							store_mutate(settings, untrack($settings)["use-yearmonth-folders"] = $$value, untrack($settings));
						},
						$$legacy: true
					}),
					'component',
					StorageSettingsPanel,
					34,
					1,
					{ componentTag: 'SettingsPanelOption' }
				);

				var node_6 = sibling(node_5, 2);

				add_svelte_meta(
					() => SettingsPanelOption(node_6, {
						get heading() {
							return $strings().object_versioning;
						},

						get description() {
							return $strings().object_versioning_desc;
						},
						toggleName: 'object-versioning',
						get toggle() {
							return $settings()["object-versioning"];
						},

						set toggle($$value) {
							store_mutate(settings, untrack($settings)["object-versioning"] = $$value, untrack($settings));
						},
						$$legacy: true
					}),
					'component',
					StorageSettingsPanel,
					41,
					1,
					{ componentTag: 'SettingsPanelOption' }
				);

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		StorageSettingsPanel,
		10,
		0,
		{ componentTag: 'Panel' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

StorageSettingsSubPage[FILENAME] = 'ui/components/StorageSettingsSubPage.svelte';

function StorageSettingsSubPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, StorageSettingsSubPage);

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => SubPage($$anchor, {
			name: 'storage-settings',
			children: wrap_snippet(StorageSettingsSubPage, ($$anchor, $$slotProps) => {
				add_svelte_meta(() => StorageSettingsPanel($$anchor, {}), 'component', StorageSettingsSubPage, 7, 1, { componentTag: 'StorageSettingsPanel' });
			}),
			$$slots: { default: true }
		}),
		'component',
		StorageSettingsSubPage,
		6,
		0,
		{ componentTag: 'SubPage' }
	);

	return pop($$exports);
}

DeliverySettingsHeadingRow[FILENAME] = 'ui/components/DeliverySettingsHeadingRow.svelte';

var root_1$h = add_locations(from_html(`<img class="svelte-1azw2tb"/> <div class="provider-details svelte-1azw2tb"><h3 class="svelte-1azw2tb"> </h3> <p class="console-details svelte-1azw2tb"><a class="console svelte-1azw2tb" target="_blank"> </a></p></div> <!>`, 1), DeliverySettingsHeadingRow[FILENAME], [[27, 1], [28, 1, [[29, 2], [30, 2, [[31, 3]]]]]]);

function DeliverySettingsHeadingRow($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, DeliverySettingsHeadingRow);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const $storage_provider = () => (
		validate_store(storage_provider, 'storage_provider'),
		store_get(storage_provider, '$storage_provider', $$stores)
	);

	const $delivery_provider = () => (
		validate_store(delivery_provider, 'delivery_provider'),
		store_get(delivery_provider, '$delivery_provider', $$stores)
	);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $settingsLocked = () => (
		validate_store(get(settingsLocked), 'settingsLocked'),
		store_get(get(settingsLocked), '$settingsLocked', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	// Parent page may want to be locked.
	let settingsLocked = tag(state(proxy(writable(false))), 'settingsLocked');

	if (hasContext("settingsLocked")) {
		store_unsub(set(settingsLocked, getContext("settingsLocked"), true), '$settingsLocked', $$stores);
	}

	let providerType = tag(user_derived(() => strict_equals($settings()["delivery-provider"], "storage") ? "storage" : "delivery"), 'providerType');

	let providerKey = tag(
		user_derived(() => strict_equals(get(providerType), "storage")
			? $storage_provider().provider_key_name
			: $delivery_provider().provider_key_name),
		'providerKey'
	);

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => PanelRow($$anchor, {
			header: true,
			gradient: true,
			get class() {
				return `delivery ${get(providerType) ?? ''} ${get(providerKey) ?? ''}`;
			},

			children: wrap_snippet(DeliverySettingsHeadingRow, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$h();
				var img = first_child(fragment_1);
				var div = sibling(img, 2);
				var h3 = child(div);
				var text$1 = child(h3, true);

				reset(h3);

				var p = sibling(h3, 2);
				var a = child(p);
				var text_1 = child(a, true);

				reset(a);
				reset(p);
				reset(div);

				var node = sibling(div, 2);

				add_svelte_meta(
					() => Button(node, {
						outline: true,
						onclick: () => push('/delivery/provider'),
						get title() {
							return $strings().edit_delivery_provider;
						},

						get disabled() {
							return $settingsLocked();
						},

						children: wrap_snippet(DeliverySettingsHeadingRow, ($$anchor, $$slotProps) => {
							next();

							var text_2 = text();

							template_effect(() => set_text(text_2, $strings().edit));
							append($$anchor, text_2);
						}),
						$$slots: { default: true }
					}),
					'component',
					DeliverySettingsHeadingRow,
					34,
					1,
					{ componentTag: 'Button' }
				);

				template_effect(() => {
					set_attribute(img, 'src', $delivery_provider().icon);
					set_attribute(img, 'alt', $delivery_provider().provider_service_name);
					set_text(text$1, $delivery_provider().provider_service_name);
					set_attribute(a, 'href', $urls().delivery_provider_console_url);
					set_attribute(a, 'title', $strings().view_provider_console);
					set_text(text_1, $delivery_provider().console_title);
				});

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		DeliverySettingsHeadingRow,
		26,
		0,
		{ componentTag: 'PanelRow' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

DeliverySettingsPanel[FILENAME] = 'ui/components/DeliverySettingsPanel.svelte';

var root_5$5 = add_locations(from_html(`<!> <!> <!>`, 1), DeliverySettingsPanel[FILENAME], []);
var root_2$9 = add_locations(from_html(`<!> <!>`, 1), DeliverySettingsPanel[FILENAME], []);
var root_1$g = add_locations(from_html(`<!> <!> <!> <!> <!>`, 1), DeliverySettingsPanel[FILENAME], []);

function DeliverySettingsPanel($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, DeliverySettingsPanel);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $delivery_provider = () => (
		validate_store(delivery_provider, 'delivery_provider'),
		store_get(delivery_provider, '$delivery_provider', $$stores)
	);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * Potentially returns a reason that the provided domain name is invalid.
	 *
	 * @param {string} domain
	 *
	 * @return {string}
	 */
	function domainValidator(domain) {
		const domainPattern = /[^a-z0-9.-]/;
		let message = "";

		if (strict_equals(domain.trim().length, 0)) {
			message = $strings().domain_blank;
		} else if (strict_equals(true, domainPattern.test(domain))) {
			message = $strings().domain_invalid_content;
		} else if (domain.length < 3) {
			message = $strings().domain_too_short;
		}

		return message;
	}

	var $$exports = { ...legacy_api() };

	init();

	add_svelte_meta(
		() => Panel($$anchor, {
			name: 'settings',
			get heading() {
				return $strings().delivery_settings_title;
			},
			helpKey: 'delivery-provider',
			children: wrap_snippet(DeliverySettingsPanel, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$g();
				var node = first_child(fragment_1);

				add_svelte_meta(() => DeliverySettingsHeadingRow(node, {}), 'component', DeliverySettingsPanel, 34, 1, { componentTag: 'DeliverySettingsHeadingRow' });

				var node_1 = sibling(node, 2);

				add_svelte_meta(() => SettingsValidationStatusRow(node_1, { section: 'delivery' }), 'component', DeliverySettingsPanel, 35, 1, { componentTag: 'SettingsValidationStatusRow' });

				var node_2 = sibling(node_1, 2);

				add_svelte_meta(
					() => SettingsPanelOption(node_2, {
						get heading() {
							return $strings().rewrite_media_urls;
						},

						get description() {
							return $delivery_provider().rewrite_media_urls_desc;
						},
						toggleName: 'serve-from-s3',
						get toggle() {
							return $settings()["serve-from-s3"];
						},

						set toggle($$value) {
							store_mutate(settings, untrack($settings)["serve-from-s3"] = $$value, untrack($settings));
						},
						$$legacy: true
					}),
					'component',
					DeliverySettingsPanel,
					36,
					1,
					{ componentTag: 'SettingsPanelOption' }
				);

				var node_3 = sibling(node_2, 2);

				{
					var consequent_2 = ($$anchor) => {
						var fragment_2 = root_2$9();
						var node_4 = first_child(fragment_2);

						add_svelte_meta(
							() => SettingsPanelOption(node_4, {
								get heading() {
									return $strings().delivery_domain;
								},

								get description() {
									return $delivery_provider().delivery_domain_desc;
								},
								toggleName: 'enable-delivery-domain',
								textName: 'delivery-domain',
								validator: domainValidator,
								get toggle() {
									return $settings()["enable-delivery-domain"];
								},

								set toggle($$value) {
									store_mutate(settings, untrack($settings)["enable-delivery-domain"] = $$value, untrack($settings));
								},

								get text() {
									return $settings()["delivery-domain"];
								},

								set text($$value) {
									store_mutate(settings, untrack($settings)["delivery-domain"] = $$value, untrack($settings));
								},
								$$legacy: true
							}),
							'component',
							DeliverySettingsPanel,
							44,
							2,
							{ componentTag: 'SettingsPanelOption' }
						);

						var node_5 = sibling(node_4, 2);

						{
							var consequent_1 = ($$anchor) => {
								add_svelte_meta(
									() => SettingsPanelOption($$anchor, {
										get heading() {
											return $delivery_provider().signed_urls_option_name;
										},

										get description() {
											return $delivery_provider().signed_urls_option_description;
										},
										toggleName: 'enable-signed-urls',
										get toggle() {
											return $settings()["enable-signed-urls"];
										},

										set toggle($$value) {
											store_mutate(settings, untrack($settings)["enable-signed-urls"] = $$value, untrack($settings));
										},

										children: wrap_snippet(DeliverySettingsPanel, ($$anchor, $$slotProps) => {
											var fragment_4 = comment();
											var node_6 = first_child(fragment_4);

											{
												var consequent = ($$anchor) => {
													var fragment_5 = root_5$5();
													var node_7 = first_child(fragment_5);

													add_svelte_meta(
														() => SettingsPanelOption(node_7, {
															get heading() {
																return $delivery_provider().signed_urls_key_id_name;
															},

															get description() {
																return $delivery_provider().signed_urls_key_id_description;
															},
															textName: 'signed-urls-key-id',
															nested: true,
															first: true,
															get text() {
																return $settings()["signed-urls-key-id"];
															},

															set text($$value) {
																store_mutate(settings, untrack($settings)["signed-urls-key-id"] = $$value, untrack($settings));
															},
															$$legacy: true
														}),
														'component',
														DeliverySettingsPanel,
														62,
														5,
														{ componentTag: 'SettingsPanelOption' }
													);

													var node_8 = sibling(node_7, 2);

													add_svelte_meta(
														() => SettingsPanelOption(node_8, {
															get heading() {
																return $delivery_provider().signed_urls_key_file_path_name;
															},

															get description() {
																return $delivery_provider().signed_urls_key_file_path_description;
															},
															textName: 'signed-urls-key-file-path',
															get placeholder() {
																return $delivery_provider().signed_urls_key_file_path_placeholder;
															},
															nested: true,
															get text() {
																return $settings()["signed-urls-key-file-path"];
															},

															set text($$value) {
																store_mutate(settings, untrack($settings)["signed-urls-key-file-path"] = $$value, untrack($settings));
															},
															$$legacy: true
														}),
														'component',
														DeliverySettingsPanel,
														71,
														5,
														{ componentTag: 'SettingsPanelOption' }
													);

													var node_9 = sibling(node_8, 2);

													add_svelte_meta(
														() => SettingsPanelOption(node_9, {
															get heading() {
																return $delivery_provider().signed_urls_object_prefix_name;
															},

															get description() {
																return $delivery_provider().signed_urls_object_prefix_description;
															},
															textName: 'signed-urls-object-prefix',
															placeholder: 'private/',
															nested: true,
															get text() {
																return $settings()["signed-urls-object-prefix"];
															},

															set text($$value) {
																store_mutate(settings, untrack($settings)["signed-urls-object-prefix"] = $$value, untrack($settings));
															},
															$$legacy: true
														}),
														'component',
														DeliverySettingsPanel,
														80,
														5,
														{ componentTag: 'SettingsPanelOption' }
													);

													append($$anchor, fragment_5);
												};

												add_svelte_meta(
													() => if_block(node_6, ($$render) => {
														if ($settings()["enable-signed-urls"]) $$render(consequent);
													}),
													'if',
													DeliverySettingsPanel,
													61,
													4
												);
											}

											append($$anchor, fragment_4);
										}),
										$$slots: { default: true },
										$$legacy: true
									}),
									'component',
									DeliverySettingsPanel,
									54,
									3,
									{ componentTag: 'SettingsPanelOption' }
								);
							};

							add_svelte_meta(
								() => if_block(node_5, ($$render) => {
									if ($delivery_provider().use_signed_urls_key_file_allowed && $settings()["enable-delivery-domain"]) $$render(consequent_1);
								}),
								'if',
								DeliverySettingsPanel,
								53,
								2
							);
						}

						append($$anchor, fragment_2);
					};

					add_svelte_meta(
						() => if_block(node_3, ($$render) => {
							if ($delivery_provider().delivery_domain_allowed) $$render(consequent_2);
						}),
						'if',
						DeliverySettingsPanel,
						43,
						1
					);
				}

				var node_10 = sibling(node_3, 2);

				add_svelte_meta(
					() => SettingsPanelOption(node_10, {
						get heading() {
							return $strings().force_https;
						},

						get description() {
							return $strings().force_https_desc;
						},
						toggleName: 'force-https',
						get toggle() {
							return $settings()["force-https"];
						},

						set toggle($$value) {
							store_mutate(settings, untrack($settings)["force-https"] = $$value, untrack($settings));
						},
						$$legacy: true
					}),
					'component',
					DeliverySettingsPanel,
					93,
					1,
					{ componentTag: 'SettingsPanelOption' }
				);

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		DeliverySettingsPanel,
		33,
		0,
		{ componentTag: 'Panel' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

DeliverySettingsSubPage[FILENAME] = 'ui/components/DeliverySettingsSubPage.svelte';

function DeliverySettingsSubPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, DeliverySettingsSubPage);

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => SubPage($$anchor, {
			name: 'delivery-settings',
			route: '/media/delivery',
			children: wrap_snippet(DeliverySettingsSubPage, ($$anchor, $$slotProps) => {
				add_svelte_meta(() => DeliverySettingsPanel($$anchor, {}), 'component', DeliverySettingsSubPage, 7, 1, { componentTag: 'DeliverySettingsPanel' });
			}),
			$$slots: { default: true }
		}),
		'component',
		DeliverySettingsSubPage,
		6,
		0,
		{ componentTag: 'SubPage' }
	);

	return pop($$exports);
}

MediaSettings[FILENAME] = 'ui/components/MediaSettings.svelte';

var root$k = add_locations(from_html(`<!> <!>`, 1), MediaSettings[FILENAME], []);

function MediaSettings($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, MediaSettings);

	var $$exports = { ...legacy_api() };
	var fragment = root$k();
	var node = first_child(fragment);

	add_svelte_meta(() => StorageSettingsSubPage(node, {}), 'component', MediaSettings, 6, 0, { componentTag: 'StorageSettingsSubPage' });

	var node_1 = sibling(node, 2);

	add_svelte_meta(() => DeliverySettingsSubPage(node_1, {}), 'component', MediaSettings, 7, 0, { componentTag: 'DeliverySettingsSubPage' });
	append($$anchor, fragment);

	return pop($$exports);
}

UrlPreview[FILENAME] = 'ui/components/UrlPreview.svelte';

var root_3$6 = add_locations(from_html(`<p> </p>`), UrlPreview[FILENAME], [[47, 3]]);
var root_5$4 = add_locations(from_html(`<div><dt> </dt> <dd> </dd></div>`), UrlPreview[FILENAME], [[52, 5, [[53, 6], [54, 6]]]]);
var root_4$3 = add_locations(from_html(`<dl></dl>`), UrlPreview[FILENAME], [[50, 3]]);
var root_2$8 = add_locations(from_html(`<!> <!>`, 1), UrlPreview[FILENAME], []);

function UrlPreview($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, UrlPreview);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $settings_changed = () => (
		validate_store(settings_changed, 'settings_changed'),
		store_get(settings_changed, '$settings_changed', $$stores)
	);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();
	let parts = tag(state(proxy($urls().url_parts)), 'parts');

	/**
	 * When settings have changed, show their preview URL, otherwise show saved settings version.
	 *
	 * Note: This function **assigns** to the `parts` variable to defeat the reactive demons!
	 *
	 * @param {Object} urls
	 * @param {boolean} settingsChanged
	 * @param {Object} settings
	 *
	 * @returns boolean
	 */
	async function temporaryUrl(urls, settingsChanged, settings) {
		if (settingsChanged) {
			const response = (await track_reactivity_loss(api.post("url-preview", { "settings": settings })))();

			// Use temporary URLs if available.
			if (response.hasOwnProperty("url_parts")) {
				set(parts, response.url_parts, true);

				return true;
			}
		}

		// Reset back to saved URLs.
		set(parts, urls.url_parts, true);

		return false;
	}

	// Update parts when settings temporarily changed.
	user_effect(() => temporaryUrl($urls(), $settings_changed(), $settings()));

	var $$exports = { ...legacy_api() };
	var fragment = comment();
	var node = first_child(fragment);

	{
		var consequent = ($$anchor) => {
			add_svelte_meta(
				() => Panel($$anchor, {
					name: 'url-preview',
					get heading() {
						return $strings().url_preview_title;
					},

					children: wrap_snippet(UrlPreview, ($$anchor, $$slotProps) => {
						var fragment_2 = root_2$8();
						var node_1 = first_child(fragment_2);

						add_svelte_meta(
							() => PanelRow(node_1, {
								class: 'desc',
								children: wrap_snippet(UrlPreview, ($$anchor, $$slotProps) => {
									var p = root_3$6();
									var text = child(p, true);

									reset(p);
									template_effect(() => set_text(text, $strings().url_preview_desc));
									append($$anchor, p);
								}),
								$$slots: { default: true }
							}),
							'component',
							UrlPreview,
							46,
							2,
							{ componentTag: 'PanelRow' }
						);

						var node_2 = sibling(node_1, 2);

						add_svelte_meta(
							() => PanelRow(node_2, {
								class: 'body flex-row',
								children: wrap_snippet(UrlPreview, ($$anchor, $$slotProps) => {
									var dl = root_4$3();

									add_svelte_meta(
										() => each(dl, 21, () => get(parts), (part) => part.title, ($$anchor, part) => {
											var div = root_5$4();
											var dt = child(div);
											var text_1 = child(dt, true);

											reset(dt);

											var dd = sibling(dt, 2);
											var text_2 = child(dd, true);

											reset(dd);
											reset(div);

											template_effect(() => {
												set_attribute(div, 'data-key', get(part).key);
												set_text(text_1, get(part).title);
												set_text(text_2, get(part).example);
											});

											transition(3, div, () => scale);
											append($$anchor, div);
										}),
										'each',
										UrlPreview,
										51,
										4
									);

									reset(dl);
									append($$anchor, dl);
								}),
								$$slots: { default: true }
							}),
							'component',
							UrlPreview,
							49,
							2,
							{ componentTag: 'PanelRow' }
						);

						append($$anchor, fragment_2);
					}),
					$$slots: { default: true }
				}),
				'component',
				UrlPreview,
				45,
				1,
				{ componentTag: 'Panel' }
			);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (get(parts).length > 0) $$render(consequent);
			}),
			'if',
			UrlPreview,
			44,
			0
		);
	}

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

/**
 * Scrolls the notifications into view.
 */
function scrollNotificationsIntoView() {
	const element = document.getElementById( "notifications" );

	if ( element ) {
		element.scrollIntoView( { behavior: "smooth", block: "start" } );
	}
}

Footer[FILENAME] = 'ui/components/Footer.svelte';

var root_1$f = add_locations(from_html(`<div class="fixed-cta-block"><div class="buttons"><!> <!></div></div>`), Footer[FILENAME], [[66, 1, [[67, 2]]]]);

function Footer($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Footer);

	const $validationErrors = () => (
		validate_store(validationErrors, 'validationErrors'),
		store_get(validationErrors, '$validationErrors', $$stores)
	);

	const $settingsChangedStore = () => (
		validate_store(settingsChangedStore(), 'settingsChangedStore'),
		store_get(settingsChangedStore(), '$settingsChangedStore', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	let settingsStore = prop($$props, 'settingsStore', 3, settings),
		settingsChangedStore = prop($$props, 'settingsChangedStore', 3, settings_changed);

	let saving = tag(state(false), 'saving');
	let disabled = tag(user_derived(() => get(saving) || $validationErrors().size > 0), 'disabled');

	// On init, start with no validation errors.
	validationErrors.set(new Map());

	/**
	 * Handles a Cancel button click.
	 */
	function handleCancel() {
		settingsStore().reset();
	}

	/**
	 * Handles a Save button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleSave() {
		set(saving, true);
		appState.pausePeriodicFetch();

		const result = (await track_reactivity_loss(settingsStore().save()))();

		store_set(revalidatingSettings, true);

		const statePromise = appState.resumePeriodicFetch();

		// The save happened, whether anything changed or not.
		if (result.hasOwnProperty("saved") && result.hasOwnProperty("changed_settings")) {
			$$props.onRouteEvent({ event: "settings.save", data: result });
		}

		// After save make sure notifications are eyeballed.
		scrollNotificationsIntoView();

		set(saving, false);

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		(await track_reactivity_loss(statePromise))();

		store_set(revalidatingSettings, false);
	}

	// On navigation away from a component showing the footer,
	// make sure settings are reset.
	onDestroy(() => settingsStore().reset());

	var $$exports = { ...legacy_api() };
	var fragment = comment();
	var node = first_child(fragment);

	{
		var consequent = ($$anchor) => {
			var div = root_1$f();
			var div_1 = child(div);
			var node_1 = child(div_1);

			add_svelte_meta(
				() => Button(node_1, {
					outline: true,
					onclick: handleCancel,
					children: wrap_snippet(Footer, ($$anchor, $$slotProps) => {
						next();

						var text$1 = text();

						template_effect(() => set_text(text$1, $strings().cancel_button));
						append($$anchor, text$1);
					}),
					$$slots: { default: true }
				}),
				'component',
				Footer,
				68,
				3,
				{ componentTag: 'Button' }
			);

			var node_2 = sibling(node_1, 2);

			add_svelte_meta(
				() => Button(node_2, {
					primary: true,
					onclick: handleSave,
					get disabled() {
						return get(disabled);
					},

					children: wrap_snippet(Footer, ($$anchor, $$slotProps) => {
						next();

						var text_1 = text();

						template_effect(() => set_text(text_1, $strings().save_changes));
						append($$anchor, text_1);
					}),
					$$slots: { default: true }
				}),
				'component',
				Footer,
				69,
				3,
				{ componentTag: 'Button' }
			);
			transition(3, div, () => slide);
			append($$anchor, div);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if ($settingsChangedStore()) $$render(consequent);
			}),
			'if',
			Footer,
			65,
			0
		);
	}

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

MediaPage[FILENAME] = 'ui/components/MediaPage.svelte';

var root_2$7 = add_locations(from_html(`<!> <!> <!> <!>`, 1), MediaPage[FILENAME], []);
var root$j = add_locations(from_html(`<!> <!> <!>`, 1), MediaPage[FILENAME], []);

function MediaPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, MediaPage);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $settings_validation = () => (
		validate_store(settings_validation, 'settings_validation'),
		store_get(settings_validation, '$settings_validation', $$stores)
	);

	const $is_plugin_setup = () => (
		validate_store(is_plugin_setup, 'is_plugin_setup'),
		store_get(is_plugin_setup, '$is_plugin_setup', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, "media");

	let sidebar = tag(state(null), 'sidebar');
	let render = tag(state(false), 'render');

	if (hasContext("sidebar")) {
		set(sidebar, getContext("sidebar"), true);
	}

	// Let all child components know if settings are currently locked.
	setContext("settingsLocked", settingsLocked);

	// We have a weird subnav here as both routes could be shown at same time.
	// So they are grouped, and CSS decides which is shown when width stops both from being shown.
	// The active route will determine the SubPage that is given the active class.
	const routes = { "*": MediaSettings };

	let items = tag(
		user_derived(() => [
			{
				route: "/",
				title: () => $strings().storage_settings_title,
				noticeIcon: $settings_validation()["storage"].type
			},

			{
				route: "/media/delivery",
				title: () => $strings().delivery_settings_title,
				noticeIcon: $settings_validation()["delivery"].type
			}
		]),
		'items'
	);

	onMount(() => {
		if ($is_plugin_setup()) {
			set(render, true);
		}
	});

	var $$exports = { ...legacy_api() };
	var fragment = root$j();
	var node = first_child(fragment);

	add_svelte_meta(
		() => Page(node, {
			get name() {
				return name();
			},

			get onRouteEvent() {
				return $$props.onRouteEvent;
			},

			children: wrap_snippet(MediaPage, ($$anchor, $$slotProps) => {
				var fragment_1 = comment();
				var node_1 = first_child(fragment_1);

				{
					var consequent = ($$anchor) => {
						var fragment_2 = root_2$7();
						var node_2 = first_child(fragment_2);

						add_svelte_meta(
							() => Notifications(node_2, {
								get tab() {
									return name();
								}
							}),
							'component',
							MediaPage,
							65,
							2,
							{ componentTag: 'Notifications' }
						);

						var node_3 = sibling(node_2, 2);

						add_svelte_meta(
							() => SubNav(node_3, {
								get name() {
									return name();
								},

								get items() {
									return get(items);
								},
								subpage: true
							}),
							'component',
							MediaPage,
							66,
							2,
							{ componentTag: 'SubNav' }
						);

						var node_4 = sibling(node_3, 2);

						add_svelte_meta(
							() => SubPages(node_4, {
								get name() {
									return name();
								},

								get routes() {
									return routes;
								}
							}),
							'component',
							MediaPage,
							67,
							2,
							{ componentTag: 'SubPages' }
						);

						var node_5 = sibling(node_4, 2);

						add_svelte_meta(() => UrlPreview(node_5, {}), 'component', MediaPage, 68, 2, { componentTag: 'UrlPreview' });
						append($$anchor, fragment_2);
					};

					add_svelte_meta(
						() => if_block(node_1, ($$render) => {
							if (get(render)) $$render(consequent);
						}),
						'if',
						MediaPage,
						64,
						1
					);
				}

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		MediaPage,
		63,
		0,
		{ componentTag: 'Page' }
	);

	var node_6 = sibling(node, 2);

	{
		var consequent_1 = ($$anchor) => {
			const SidebarComponent = tag(user_derived(() => get(sidebar)), 'SidebarComponent');

			get(SidebarComponent);

			var fragment_3 = comment();
			var node_7 = first_child(fragment_3);

			add_svelte_meta(
				() => component(node_7, () => get(SidebarComponent), ($$anchor, SidebarComponent_1) => {
					SidebarComponent_1($$anchor, {});
				}),
				'component',
				MediaPage,
				74,
				1,
				{ componentTag: 'SidebarComponent' }
			);

			append($$anchor, fragment_3);
		};

		add_svelte_meta(
			() => if_block(node_6, ($$render) => {
				if (get(sidebar) && get(render)) $$render(consequent_1);
			}),
			'if',
			MediaPage,
			72,
			0
		);
	}

	var node_8 = sibling(node_6, 2);

	add_svelte_meta(
		() => Footer(node_8, {
			get onRouteEvent() {
				return $$props.onRouteEvent;
			}
		}),
		'component',
		MediaPage,
		77,
		0,
		{ componentTag: 'Footer' }
	);

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

StoragePage[FILENAME] = 'ui/components/StoragePage.svelte';

var root_1$e = add_locations(from_html(`<!> <!> <!>`, 1), StoragePage[FILENAME], []);

function StoragePage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, StoragePage);

	const $current_settings = () => (
		validate_store(current_settings, 'current_settings'),
		store_get(current_settings, '$current_settings', $$stores)
	);

	const $needs_access_keys = () => (
		validate_store(needs_access_keys, 'needs_access_keys'),
		store_get(needs_access_keys, '$needs_access_keys', $$stores)
	);

	const $location = () => (
		validate_store(location$1, 'location'),
		store_get(location$1, '$location', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, "storage");

	// During initial setup some storage sub-pages behave differently.
	// Not having a bucket defined is akin to initial setup, but changing provider in sub page may also flip the switch.
	if ($current_settings().bucket) {
		setContext("initialSetup", false);
	} else {
		setContext("initialSetup", true);
	}

	// Let all child components know if settings are currently locked.
	setContext("settingsLocked", settingsLocked);

	const prefix = "/storage";
	let items = tag(state(proxy(pages.withPrefix(prefix))), 'items');
	let routes = tag(state(proxy(pages.routes(prefix))), 'routes');

	user_effect(() => {
		set(items, pages.withPrefix(prefix), true);
		set(routes, pages.routes(prefix), true);

		// Ensure only Storage Provider subpage can be visited if credentials not set.
		if ($needs_access_keys() && $location().startsWith("/storage/") && strict_equals($location(), "/storage/provider", false)) {
			push("/storage/provider");
		}
	});

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => Page($$anchor, {
			get name() {
				return name();
			},
			subpage: true,
			get onRouteEvent() {
				return $$props.onRouteEvent;
			},

			children: wrap_snippet(StoragePage, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$e();
				var node = first_child(fragment_1);

				add_svelte_meta(() => Notifications(node, { tab: 'media', tabParent: 'media' }), 'component', StoragePage, 52, 1, { componentTag: 'Notifications' });

				var node_1 = sibling(node, 2);

				add_svelte_meta(
					() => SubNav(node_1, {
						get name() {
							return name();
						},

						get items() {
							return get(items);
						},
						progress: true
					}),
					'component',
					StoragePage,
					54,
					1,
					{ componentTag: 'SubNav' }
				);

				var node_2 = sibling(node_1, 2);

				add_svelte_meta(
					() => SubPages(node_2, {
						get name() {
							return name();
						},
						prefix,
						get routes() {
							return get(routes);
						},

						get onRouteEvent() {
							return $$props.onRouteEvent;
						}
					}),
					'component',
					StoragePage,
					56,
					1,
					{ componentTag: 'SubPages' }
				);

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		StoragePage,
		51,
		0,
		{ componentTag: 'Page' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

/**
 * Determines whether a page should be refreshed due to changes to settings.
 *
 * @param {boolean} saving
 * @param {object} previousSettings
 * @param {object} currentSettings
 * @param {object} previousDefines
 * @param {object} currentDefines
 *
 * @returns {boolean}
 */
function needsRefresh( saving, previousSettings, currentSettings, previousDefines, currentDefines ) {
	if ( saving ) {
		return false;
	}

	if ( objectsDiffer( [previousSettings, currentSettings] ) ) {
		return true;
	}

	return objectsDiffer( [previousDefines, currentDefines] );
}

TabButton[FILENAME] = 'ui/components/TabButton.svelte';

var root_1$d = add_locations(from_html(`<img type="image/svg+xml"/>`), TabButton[FILENAME], [[36, 2]]);
var root_2$6 = add_locations(from_html(`<p> </p>`), TabButton[FILENAME], [[43, 2]]);
var root_3$5 = add_locations(from_html(`<img class="checkmark" type="image/svg+xml"/>`), TabButton[FILENAME], [[46, 2]]);
var root$i = add_locations(from_html(`<a><!> <!> <!></a>`), TabButton[FILENAME], [[27, 0]]);

function TabButton($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, TabButton);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {boolean} [active]
	 * @property {boolean} [disabled]
	 * @property {string} [icon]
	 * @property {string} [iconDesc]
	 * @property {string} [text]
	 * @property {string} [url]
	 * @property {function} [onclick]
	 */
	/** @type {Props} */
	let active = prop($$props, 'active', 3, false),
		disabled = prop($$props, 'disabled', 3, false),
		icon = prop($$props, 'icon', 3, ""),
		iconDesc = prop($$props, 'iconDesc', 3, ""),
		text = prop($$props, 'text', 3, ""),
		url = prop($$props, 'url', 19, () => $urls().settings);

	var $$exports = { ...legacy_api() };
	var a = root$i();
	let classes;
	var node = child(a);

	{
		var consequent = ($$anchor) => {
			var img = root_1$d();

			template_effect(() => {
				set_attribute(img, 'src', icon());
				set_attribute(img, 'alt', iconDesc());
			});

			append($$anchor, img);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (icon()) $$render(consequent);
			}),
			'if',
			TabButton,
			35,
			1
		);
	}

	var node_1 = sibling(node, 2);

	{
		var consequent_1 = ($$anchor) => {
			var p = root_2$6();
			var text_1 = child(p);
			template_effect(() => set_text(text_1, text()));
			append($$anchor, p);
		};

		add_svelte_meta(
			() => if_block(node_1, ($$render) => {
				if (text()) $$render(consequent_1);
			}),
			'if',
			TabButton,
			42,
			1
		);
	}

	var node_2 = sibling(node_1, 2);

	{
		var consequent_2 = ($$anchor) => {
			var img_1 = root_3$5();

			template_effect(() => {
				set_attribute(img_1, 'src', $urls().assets + 'img/icon/licence-checked.svg');
				set_attribute(img_1, 'alt', $strings().selected_desc);
			});

			append($$anchor, img_1);
		};

		add_svelte_meta(
			() => if_block(node_2, ($$render) => {
				if (active()) $$render(consequent_2);
			}),
			'if',
			TabButton,
			45,
			1
		);
	}

	template_effect(() => {
		set_attribute(a, 'href', url());
		classes = set_class(a, 1, 'button-tab', null, classes, { active: active(), 'btn-disabled': disabled() });
		a.disabled = disabled();
	});

	delegated('click', a, function (...$$args) {
		apply(() => $$props.onclick, this, $$args, TabButton, [33, 2]);
	});

	append($$anchor, a);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

delegate(['click']);

RadioButton[FILENAME] = 'ui/components/RadioButton.svelte';

var root_1$c = add_locations(from_html(`<p class="radio-desc"></p>`), RadioButton[FILENAME], [[32, 1]]);
var root$h = add_locations(from_html(`<div><label><input type="radio"/> <!></label></div> <!>`, 1), RadioButton[FILENAME], [[25, 0, [[26, 1, [[27, 2]]]]]]);

function RadioButton($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, RadioButton);

	const binding_group = [];

	/**
	 * @typedef {Object} Props
	 * @property {boolean} [list]
	 * @property {boolean} [disabled]
	 * @property {string} [name]
	 * @property {string} [value]
	 * @property {string} [selected]
	 * @property {string} [desc]
	 * @property {import("svelte").Snippet} [children]
	 */
	/** @type {Props} */
	let list = prop($$props, 'list', 3, false),
		disabled = prop($$props, 'disabled', 3, false),
		name = prop($$props, 'name', 3, "options"),
		value = prop($$props, 'value', 3, ""),
		selected = prop($$props, 'selected', 15, ""),
		desc = prop($$props, 'desc', 3, "");

	var $$exports = { ...legacy_api() };
	var fragment = root$h();
	var div = first_child(fragment);
	let classes;
	var label = child(div);
	var input = child(label);

	var input_value;
	var node = sibling(input, 2);

	add_svelte_meta(() => snippet(node, () => $$props.children ?? noop), 'render', RadioButton, 28, 2);

	var node_1 = sibling(div, 2);

	{
		var consequent = ($$anchor) => {
			var p = root_1$c();

			html(p, desc, true);
			append($$anchor, p);
		};

		add_svelte_meta(
			() => if_block(node_1, ($$render) => {
				if (strict_equals(selected(), value()) && desc()) $$render(consequent);
			}),
			'if',
			RadioButton,
			31,
			0
		);
	}

	template_effect(() => {
		classes = set_class(div, 1, 'radio-btn', null, classes, { list: list(), disabled: disabled() });
		set_attribute(input, 'name', name());
		input.disabled = disabled();

		if (input_value !== (input_value = value())) {
			input.value = (input.__value = value()) ?? '';
		}
	});

	bind_group(
		binding_group,
		[],
		input,
		() => {
			value();

			return selected();
		},
		function set($$value) {
			selected($$value);
		}
	);

	append($$anchor, fragment);

	return pop($$exports);
}

AccessKeysDefine[FILENAME] = 'ui/components/AccessKeysDefine.svelte';

var root$g = add_locations(from_html(`<p></p> <pre> </pre>`, 1), AccessKeysDefine[FILENAME], [[5, 0], [7, 0]]);

function AccessKeysDefine($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, AccessKeysDefine);

	var $$exports = { ...legacy_api() };
	var fragment = root$g();
	var p = first_child(fragment);

	html(p, () => $$props.provider.define_access_keys_desc, true);

	var pre = sibling(p, 2);
	var text = child(pre);
	template_effect(() => set_text(text, $$props.provider.define_access_keys_example));
	append($$anchor, fragment);

	return pop($$exports);
}

BackNextButtonsRow[FILENAME] = 'ui/components/BackNextButtonsRow.svelte';

var root$f = add_locations(from_html(`<div class="btn-row"><!> <!> <!></div>`), BackNextButtonsRow[FILENAME], [[69, 0]]);

function BackNextButtonsRow($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, BackNextButtonsRow);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {any} [backText]
	 * @property {boolean} [backDisabled]
	 * @property {string} [backTitle]
	 * @property {boolean} [backVisible]
	 * @property {any} [skipText]
	 * @property {boolean} [skipDisabled]
	 * @property {string} [skipTitle]
	 * @property {boolean} [skipVisible]
	 * @property {any} [nextText]
	 * @property {boolean} [nextDisabled]
	 * @property {string} [nextTitle]
	 * @property {function} [onBack]
	 * @property {function} [onSkip]
	 * @property {function} [onNext]
	 */
	/** @type {Props} */
	let backText = prop($$props, 'backText', 19, () => $strings().back),
		backDisabled = prop($$props, 'backDisabled', 3, false),
		backTitle = prop($$props, 'backTitle', 3, ""),
		backVisible = prop($$props, 'backVisible', 3, false),
		skipText = prop($$props, 'skipText', 19, () => $strings().skip),
		skipDisabled = prop($$props, 'skipDisabled', 3, false),
		skipTitle = prop($$props, 'skipTitle', 3, ""),
		skipVisible = prop($$props, 'skipVisible', 3, false),
		nextText = prop($$props, 'nextText', 19, () => $strings().next),
		nextDisabled = prop($$props, 'nextDisabled', 3, false),
		nextTitle = prop($$props, 'nextTitle', 3, ""),
		onBack = prop($$props, 'onBack', 19, () => ({})),
		onSkip = prop($$props, 'onSkip', 19, () => ({})),
		onNext = prop($$props, 'onNext', 19, () => ({}));

	/**
	 * Handle back request, uses onBack callback if set.
	 */
	function handleBack() {
		if (strict_equals(typeof onBack(), "function")) {
			onBack()();
		}
	}

	/**
	 * Handle skip request, uses onSkip callback if set.
	 */
	function handleSkip() {
		if (strict_equals(typeof onSkip(), "function")) {
			onSkip()();
		}
	}

	/**
	 * Handle next request, uses onNext callback if set.
	 */
	function handleNext() {
		if (strict_equals(typeof onNext(), "function")) {
			onNext()();
		}
	}

	var $$exports = { ...legacy_api() };
	var div = root$f();
	var node = child(div);

	{
		var consequent = ($$anchor) => {
			add_svelte_meta(
				() => Button($$anchor, {
					large: true,
					onclick: handleBack,
					get disabled() {
						return backDisabled();
					},

					get title() {
						return backTitle();
					},

					children: wrap_snippet(BackNextButtonsRow, ($$anchor, $$slotProps) => {
						next();

						var text$1 = text();

						template_effect(() => set_text(text$1, backText()));
						append($$anchor, text$1);
					}),
					$$slots: { default: true }
				}),
				'component',
				BackNextButtonsRow,
				71,
				2,
				{ componentTag: 'Button' }
			);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (backVisible()) $$render(consequent);
			}),
			'if',
			BackNextButtonsRow,
			70,
			1
		);
	}

	var node_1 = sibling(node, 2);

	{
		var consequent_1 = ($$anchor) => {
			add_svelte_meta(
				() => Button($$anchor, {
					large: true,
					outline: true,
					onclick: handleSkip,
					get disabled() {
						return skipDisabled();
					},

					get title() {
						return skipTitle();
					},

					children: wrap_snippet(BackNextButtonsRow, ($$anchor, $$slotProps) => {
						next();

						var text_1 = text();

						template_effect(() => set_text(text_1, skipText()));
						append($$anchor, text_1);
					}),
					$$slots: { default: true }
				}),
				'component',
				BackNextButtonsRow,
				81,
				2,
				{ componentTag: 'Button' }
			);
		};

		add_svelte_meta(
			() => if_block(node_1, ($$render) => {
				if (skipVisible()) $$render(consequent_1);
			}),
			'if',
			BackNextButtonsRow,
			80,
			1
		);
	}

	var node_2 = sibling(node_1, 2);

	add_svelte_meta(
		() => Button(node_2, {
			large: true,
			primary: true,
			onclick: handleNext,
			get disabled() {
				return nextDisabled();
			},

			get title() {
				return nextTitle();
			},

			children: wrap_snippet(BackNextButtonsRow, ($$anchor, $$slotProps) => {
				next();

				var text_2 = text();

				template_effect(() => set_text(text_2, nextText()));
				append($$anchor, text_2);
			}),
			$$slots: { default: true }
		}),
		'component',
		BackNextButtonsRow,
		91,
		1,
		{ componentTag: 'Button' }
	);
	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

KeyFileDefine[FILENAME] = 'ui/components/KeyFileDefine.svelte';

var root$e = add_locations(from_html(`<p></p> <pre> </pre>`, 1), KeyFileDefine[FILENAME], [[5, 0], [7, 0]]);

function KeyFileDefine($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, KeyFileDefine);

	var $$exports = { ...legacy_api() };
	var fragment = root$e();
	var p = first_child(fragment);

	html(p, () => $$props.provider.define_key_file_desc, true);

	var pre = sibling(p, 2);
	var text = child(pre);
	template_effect(() => set_text(text, $$props.provider.define_key_file_example));
	append($$anchor, fragment);

	return pop($$exports);
}

UseServerRolesDefine[FILENAME] = 'ui/components/UseServerRolesDefine.svelte';

var root$d = add_locations(from_html(`<p></p> <pre> </pre>`, 1), UseServerRolesDefine[FILENAME], [[5, 0], [7, 0]]);

function UseServerRolesDefine($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, UseServerRolesDefine);

	var $$exports = { ...legacy_api() };
	var fragment = root$d();
	var p = first_child(fragment);

	html(p, () => $$props.provider.use_server_roles_desc, true);

	var pre = sibling(p, 2);
	var text = child(pre);
	template_effect(() => set_text(text, $$props.provider.use_server_roles_example));
	append($$anchor, fragment);

	return pop($$exports);
}

AccessKeysEntry[FILENAME] = 'ui/components/AccessKeysEntry.svelte';

var root$c = add_locations(from_html(`<p></p> <label class="input-label"> </label> <input type="text" minlength="20" size="20"/> <label class="input-label"> </label> <input type="text" autocomplete="off" minlength="40" size="40"/>`, 1), AccessKeysEntry[FILENAME], [[27, 0], [29, 0], [30, 0], [41, 0], [42, 0]]);

function AccessKeysEntry($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, AccessKeysEntry);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {any} provider
	 * @property {string} [accessKeyId]
	 * @property {string} [secretAccessKey]
	 * @property {boolean} [disabled]
	 */
	/** @type {Props} */
	let accessKeyId = prop($$props, 'accessKeyId', 15, ""),
		secretAccessKey = prop($$props, 'secretAccessKey', 15, ""),
		disabled = prop($$props, 'disabled', 3, false);

	let accessKeyIdName = "access-key-id";
	let accessKeyIdLabel = $strings().access_key_id;
	let secretAccessKeyName = "secret-access-key";
	let secretAccessKeyLabel = $strings().secret_access_key;
	var $$exports = { ...legacy_api() };
	var fragment = root$c();
	var p = first_child(fragment);

	html(p, () => $$props.provider.enter_access_keys_desc, true);

	var label = sibling(p, 2);

	set_attribute(label, 'for', accessKeyIdName);

	var text = child(label);

	var input = sibling(label, 2);
	set_attribute(input, 'id', accessKeyIdName);
	set_attribute(input, 'name', accessKeyIdName);

	let classes;
	var label_1 = sibling(input, 2);

	set_attribute(label_1, 'for', secretAccessKeyName);

	var text_1 = child(label_1);

	var input_1 = sibling(label_1, 2);
	set_attribute(input_1, 'id', secretAccessKeyName);
	set_attribute(input_1, 'name', secretAccessKeyName);

	let classes_1;

	template_effect(() => {
		set_text(text, accessKeyIdLabel);
		input.disabled = disabled();
		classes = set_class(input, 1, '', null, classes, { disabled: disabled() });
		set_text(text_1, secretAccessKeyLabel);
		input_1.disabled = disabled();
		classes_1 = set_class(input_1, 1, '', null, classes_1, { disabled: disabled() });
	});

	bind_value(
		input,
		function get() {
			return accessKeyId();
		},
		function set($$value) {
			accessKeyId($$value);
		}
	);

	bind_value(
		input_1,
		function get() {
			return secretAccessKey();
		},
		function set($$value) {
			secretAccessKey($$value);
		}
	);

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

KeyFileEntry[FILENAME] = 'ui/components/KeyFileEntry.svelte';

var root$b = add_locations(from_html(`<p></p> <label class="input-label"> </label> <textarea rows="10"></textarea>`, 1), KeyFileEntry[FILENAME], [[18, 0], [20, 0], [21, 0]]);

function KeyFileEntry($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, KeyFileEntry);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {any} provider
	 * @property {string} [value]
	 * @property {boolean} [disabled]
	 */
	/** @type {Props} */
	let value = prop($$props, 'value', 15, ""),
		disabled = prop($$props, 'disabled', 3, false);

	let name = "key-file";
	let label = $strings().key_file;
	var $$exports = { ...legacy_api() };
	var fragment = root$b();
	var p = first_child(fragment);

	html(p, () => $$props.provider.enter_key_file_desc, true);

	var label_1 = sibling(p, 2);

	set_attribute(label_1, 'for', name);

	var text = child(label_1);

	var textarea = sibling(label_1, 2);
	set_attribute(textarea, 'id', name);
	set_attribute(textarea, 'name', name);

	let classes;

	template_effect(() => {
		set_text(text, label);
		textarea.disabled = disabled();
		classes = set_class(textarea, 1, '', null, classes, { disabled: disabled() });
	});

	bind_value(
		textarea,
		function get() {
			return value();
		},
		function set($$value) {
			value($$value);
		}
	);

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

StorageProviderSubPage[FILENAME] = 'ui/components/StorageProviderSubPage.svelte';

var root_3$4 = add_locations(from_html(`<p></p>`), StorageProviderSubPage[FILENAME], [[219, 3]]);
var root_8$3 = add_locations(from_html(`<p></p>`), StorageProviderSubPage[FILENAME], [[239, 4]]);
var root_5$3 = add_locations(from_html(`<!> <!>`, 1), StorageProviderSubPage[FILENAME], []);
var root_10$1 = add_locations(from_html(`<!> <!> <!>`, 1), StorageProviderSubPage[FILENAME], []);
var root_1$b = add_locations(from_html(`<!> <!> <!> <!> <!>`, 1), StorageProviderSubPage[FILENAME], []);

function StorageProviderSubPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, StorageProviderSubPage);

	const $current_settings = () => (
		validate_store(current_settings, 'current_settings'),
		store_get(current_settings, '$current_settings', $$stores)
	);

	const $defined_settings = () => (
		validate_store(defined_settings, 'defined_settings'),
		store_get(defined_settings, '$defined_settings', $$stores)
	);

	const $needs_refresh = () => (
		validate_store(needs_refresh, 'needs_refresh'),
		store_get(needs_refresh, '$needs_refresh', $$stores)
	);

	const $storage_provider = () => (
		validate_store(storage_provider, 'storage_provider'),
		store_get(storage_provider, '$storage_provider', $$stores)
	);

	const $settingsLocked = () => (
		validate_store(get(settingsLocked), 'settingsLocked'),
		store_get(get(settingsLocked), '$settingsLocked', $$stores)
	);

	const $counts = () => (
		validate_store(counts, 'counts'),
		store_get(counts, '$counts', $$stores)
	);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $storage_providers = () => (
		validate_store(storage_providers, 'storage_providers'),
		store_get(storage_providers, '$storage_providers', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	// Parent page may want to be locked.
	let settingsLocked = tag(state(proxy(writable(false))), 'settingsLocked');

	if (hasContext("settingsLocked")) {
		store_unsub(set(settingsLocked, getContext("settingsLocked"), true), '$settingsLocked', $$stores);
	}

	// Need to be careful about throwing unneeded warnings.
	let initialSettings = tag(state(proxy($current_settings())), 'initialSettings');

	if (hasContext("initialSettings")) {
		set(initialSettings, getContext("initialSettings"), true);
	}

	// As this page does not directly alter the settings store until done,
	// we need to keep track of any changes made elsewhere and prompt
	// the user to refresh the page.
	let saving = tag(state(false), 'saving');

	const previousSettings = { ...$current_settings() };
	const previousDefines = { ...$defined_settings() };

	user_pre_effect(() => {
		store_set(needs_refresh, $needs_refresh() || needsRefresh(get(saving), previousSettings, $current_settings(), previousDefines, $defined_settings()));
	});

	/*
	 * 1. Select Storage Provider
	 */
	let storageProvider = tag(state(proxy({ ...$storage_provider() })), 'storageProvider');

	let defined = tag(user_derived(() => $defined_settings().includes("provider")), 'defined');
	let disabled = tag(user_derived(() => get(defined) || $needs_refresh() || $settingsLocked()), 'disabled');

	/**
	 * Handles picking different storage provider.
	 *
	 * @param {Object} provider
	 */
	function handleChooseProvider(provider) {
		if (get(disabled)) {
			return;
		}

		set(storageProvider, provider, true);

		// Now make sure authMethod is valid for chosen storage provider.
		set(authMethod, getAuthMethod(get(storageProvider), get(authMethod)), true);
	}

	let changedWithOffloaded = tag(user_derived(() => strict_equals(get(initialSettings).provider, get(storageProvider).provider_key_name, false) && $counts().offloaded > 0), 'changedWithOffloaded');

	/*
	 * 2. Select Authentication method
	 */
	let accessKeyId = tag(state(proxy($settings()["access-key-id"])), 'accessKeyId');

	let secretAccessKey = tag(state(proxy($settings()["secret-access-key"])), 'secretAccessKey');
	let keyFile = tag(state(proxy($settings()["key-file"] ? JSON.stringify($settings()["key-file"]) : "")), 'keyFile');

	/**
	 * For the given current storage provider, determine the authentication method or fallback to currently selected.
	 * It's possible that the storage provider can be freely changed but the
	 * authentication method is defined (fixed) differently for each, or freely changeable too.
	 * The order of evaluation in this function is important and mirrors the server side evaluation order.
	 *
	 * @param {provider} provider
	 * @param {string} current auth method, one of "define", "server-role" or "database" if set.
	 *
	 * @return {string}
	 */
	function getAuthMethod(provider, current = "") {
		if (provider.use_access_keys_allowed && provider.used_access_keys_constants.length) {
			return "define";
		}

		if (provider.use_key_file_allowed && provider.used_key_file_path_constants.length) {
			return "define";
		}

		if (provider.use_server_roles_allowed && provider.used_server_roles_constants.length) {
			return "server-role";
		}

		if (strict_equals(current, "server-role") && !provider.use_server_roles_allowed) {
			return "define";
		}

		if (strict_equals(current.length, 0)) {
			if (provider.use_access_keys_allowed && (get(accessKeyId) || get(secretAccessKey))) {
				return "database";
			}

			if (provider.use_key_file_allowed && get(keyFile)) {
				return "database";
			}

			if (provider.use_server_roles_allowed && $settings()["use-server-roles"]) {
				return "server-role";
			}

			// Default to most secure option.
			return "define";
		}

		return current;
	}

	// Set initial auth method.
	// svelte-ignore state_referenced_locally
	let authMethod = tag(state(proxy(getAuthMethod(get(storageProvider)))), 'authMethod');

	// If auth method is not allowed to be database, then either define or server-role is being forced, likely by a define.
	let authDefined = tag(user_derived(() => strict_equals("database", getAuthMethod(get(storageProvider), "database"), false)), 'authDefined');

	let authDisabled = tag(user_derived(() => get(authDefined) || $needs_refresh() || $settingsLocked()), 'authDisabled');

	/*
	 * 3. Save Authentication Credentials
	 */
	/**
	 * Returns a title string to be used for the credentials panel as appropriate for the auth method.
	 *
	 * @param {string} method
	 * @return {*}
	 */
	function getCredentialsTitle(method) {
		return $strings().auth_method_title[method];
	}

	let saveCredentialsTitle = tag(user_derived(() => getCredentialsTitle(get(authMethod))), 'saveCredentialsTitle');

	/*
	 * Do Something!
	 */
	/**
	 * Handles a Next button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleNext() {
		set(saving, true);
		appState.pausePeriodicFetch();
		store_mutate(settings, untrack($settings).provider = get(storageProvider).provider_key_name, untrack($settings));
		store_mutate(settings, untrack($settings)["access-key-id"] = get(accessKeyId), untrack($settings));
		store_mutate(settings, untrack($settings)["secret-access-key"] = get(secretAccessKey), untrack($settings));
		store_mutate(settings, untrack($settings)["use-server-roles"] = strict_equals(get(authMethod), "server-role"), untrack($settings));
		store_mutate(settings, untrack($settings)["key-file"] = get(keyFile), untrack($settings));

		const result = (await track_reactivity_loss(settings.save()))();

		// If something went wrong, don't move onto next step.
		if (!result.hasOwnProperty("saved") || !result.saved) {
			settings.reset();
			set(saving, false);
			(await track_reactivity_loss(appState.resumePeriodicFetch()))();
			scrollNotificationsIntoView();

			return;
		}

		store_set(revalidatingSettings, true);

		const statePromise = appState.resumePeriodicFetch();

		$$props.onRouteEvent({ event: "settings.save", data: result });

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		(await track_reactivity_loss(statePromise))();

		store_set(revalidatingSettings, false);
	}

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => SubPage($$anchor, {
			name: 'storage-provider-settings',
			route: '/storage/provider',
			children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$b();
				var node = first_child(fragment_1);

				{
					var consequent = ($$anchor) => {
						add_svelte_meta(
							() => Notification($$anchor, {
								inline: true,
								warning: true,
								get heading() {
									return get(storageProvider).media_already_offloaded_warning.heading;
								},

								children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
									var p = root_3$4();

									html(p, () => get(storageProvider).media_already_offloaded_warning.message, true);
									reset(p);
									append($$anchor, p);
								}),
								$$slots: { default: true }
							}),
							'component',
							StorageProviderSubPage,
							218,
							2,
							{ componentTag: 'Notification' }
						);
					};

					add_svelte_meta(
						() => if_block(node, ($$render) => {
							if (get(changedWithOffloaded)) $$render(consequent);
						}),
						'if',
						StorageProviderSubPage,
						217,
						1
					);
				}

				var node_1 = sibling(node, 2);

				add_svelte_meta(
					() => Panel(node_1, {
						get heading() {
							return $strings().select_storage_provider_title;
						},

						get defined() {
							return get(defined);
						},
						multi: true,
						children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
							add_svelte_meta(
								() => PanelRow($$anchor, {
									class: 'body flex-row tab-buttons',
									children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
										var fragment_4 = root_5$3();
										var node_2 = first_child(fragment_4);

										add_svelte_meta(
											() => each(node_2, 1, () => Object.values($storage_providers()), index, ($$anchor, provider) => {
												var fragment_5 = comment();
												var node_3 = first_child(fragment_5);

												{
													var consequent_1 = ($$anchor) => {
														{
															let $0 = user_derived(() => strict_equals(get(provider).provider_key_name, get(storageProvider).provider_key_name));

															add_svelte_meta(
																() => TabButton($$anchor, {
																	get active() {
																		return get($0);
																	},

																	get disabled() {
																		return get(disabled);
																	},

																	get icon() {
																		return get(provider).icon;
																	},

																	get iconDesc() {
																		return get(provider).icon_desc;
																	},

																	get text() {
																		return get(provider).provider_service_name;
																	},

																	onclick: (event) => {
																		event.preventDefault();
																		handleChooseProvider(get(provider));
																	}
																}),
																'component',
																StorageProviderSubPage,
																227,
																5,
																{ componentTag: 'TabButton' }
															);
														}
													};

													add_svelte_meta(
														() => if_block(node_3, ($$render) => {
															if (!get(provider).is_deprecated || strict_equals(get(provider).provider_key_name, get(storageProvider).provider_key_name)) $$render(consequent_1);
														}),
														'if',
														StorageProviderSubPage,
														226,
														4
													);
												}

												append($$anchor, fragment_5);
											}),
											'each',
											StorageProviderSubPage,
											225,
											3
										);

										var node_4 = sibling(node_2, 2);

										add_svelte_meta(
											() => Notification(node_4, {
												class: 'notice-qsg',
												children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
													var p_1 = root_8$3();

													html(p_1, () => get(storageProvider).get_access_keys_help, true);
													reset(p_1);
													append($$anchor, p_1);
												}),
												$$slots: { default: true }
											}),
											'component',
											StorageProviderSubPage,
											238,
											3,
											{ componentTag: 'Notification' }
										);

										append($$anchor, fragment_4);
									}),
									$$slots: { default: true }
								}),
								'component',
								StorageProviderSubPage,
								224,
								2,
								{ componentTag: 'PanelRow' }
							);
						}),
						$$slots: { default: true }
					}),
					'component',
					StorageProviderSubPage,
					223,
					1,
					{ componentTag: 'Panel' }
				);

				var node_5 = sibling(node_1, 2);

				add_svelte_meta(
					() => Panel(node_5, {
						get heading() {
							return $strings().select_auth_method_title;
						},

						get defined() {
							return get(authDefined);
						},
						multi: true,
						children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
							add_svelte_meta(
								() => PanelRow($$anchor, {
									class: 'body flex-column',
									children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
										var fragment_8 = root_10$1();
										var node_6 = first_child(fragment_8);

										{
											var consequent_2 = ($$anchor) => {
												add_svelte_meta(
													() => RadioButton($$anchor, {
														get disabled() {
															return get(authDisabled);
														},
														value: 'define',
														get desc() {
															return get(storageProvider).defined_auth_desc;
														},

														get selected() {
															return get(authMethod);
														},

														set selected($$value) {
															set(authMethod, $$value, true);
														},

														children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
															next();

															var text$1 = text();

															template_effect(() => set_text(text$1, $strings().define_access_keys));
															append($$anchor, text$1);
														}),
														$$slots: { default: true }
													}),
													'component',
													StorageProviderSubPage,
													248,
													4,
													{ componentTag: 'RadioButton' }
												);
											};

											var consequent_3 = ($$anchor) => {
												add_svelte_meta(
													() => RadioButton($$anchor, {
														get disabled() {
															return get(authDisabled);
														},
														value: 'define',
														get desc() {
															return get(storageProvider).defined_auth_desc;
														},

														get selected() {
															return get(authMethod);
														},

														set selected($$value) {
															set(authMethod, $$value, true);
														},

														children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
															next();

															var text_1 = text();

															template_effect(() => set_text(text_1, $strings().define_key_file_path));
															append($$anchor, text_1);
														}),
														$$slots: { default: true }
													}),
													'component',
													StorageProviderSubPage,
													252,
													4,
													{ componentTag: 'RadioButton' }
												);
											};

											add_svelte_meta(
												() => if_block(node_6, ($$render) => {
													if (get(storageProvider).use_access_keys_allowed) $$render(consequent_2); else if (get(storageProvider).use_key_file_allowed) $$render(consequent_3, 1);
												}),
												'if',
												StorageProviderSubPage,
												247,
												3
											);
										}

										var node_7 = sibling(node_6, 2);

										{
											var consequent_4 = ($$anchor) => {
												add_svelte_meta(
													() => RadioButton($$anchor, {
														get disabled() {
															return get(authDisabled);
														},
														value: 'server-role',
														get desc() {
															return get(storageProvider).defined_auth_desc;
														},

														get selected() {
															return get(authMethod);
														},

														set selected($$value) {
															set(authMethod, $$value, true);
														},

														children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
															next();

															var text_2 = text();

															template_effect(() => set_text(text_2, get(storageProvider).use_server_roles_title));
															append($$anchor, text_2);
														}),
														$$slots: { default: true }
													}),
													'component',
													StorageProviderSubPage,
													259,
													4,
													{ componentTag: 'RadioButton' }
												);
											};

											add_svelte_meta(
												() => if_block(node_7, ($$render) => {
													if (get(storageProvider).use_server_roles_allowed) $$render(consequent_4);
												}),
												'if',
												StorageProviderSubPage,
												258,
												3
											);
										}

										var node_8 = sibling(node_7, 2);

										{
											var consequent_5 = ($$anchor) => {
												add_svelte_meta(
													() => RadioButton($$anchor, {
														get disabled() {
															return get(authDisabled);
														},
														value: 'database',
														get selected() {
															return get(authMethod);
														},

														set selected($$value) {
															set(authMethod, $$value, true);
														},

														children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
															next();

															var text_3 = text();

															template_effect(() => set_text(text_3, $strings().store_access_keys_in_db));
															append($$anchor, text_3);
														}),
														$$slots: { default: true }
													}),
													'component',
													StorageProviderSubPage,
													266,
													4,
													{ componentTag: 'RadioButton' }
												);
											};

											var consequent_6 = ($$anchor) => {
												add_svelte_meta(
													() => RadioButton($$anchor, {
														get disabled() {
															return get(authDisabled);
														},
														value: 'database',
														get selected() {
															return get(authMethod);
														},

														set selected($$value) {
															set(authMethod, $$value, true);
														},

														children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
															next();

															var text_4 = text();

															template_effect(() => set_text(text_4, $strings().store_key_file_in_db));
															append($$anchor, text_4);
														}),
														$$slots: { default: true }
													}),
													'component',
													StorageProviderSubPage,
													270,
													4,
													{ componentTag: 'RadioButton' }
												);
											};

											add_svelte_meta(
												() => if_block(node_8, ($$render) => {
													if (get(storageProvider).use_access_keys_allowed) $$render(consequent_5); else if (get(storageProvider).use_key_file_allowed) $$render(consequent_6, 1);
												}),
												'if',
												StorageProviderSubPage,
												265,
												3
											);
										}

										append($$anchor, fragment_8);
									}),
									$$slots: { default: true }
								}),
								'component',
								StorageProviderSubPage,
								245,
								2,
								{ componentTag: 'PanelRow' }
							);
						}),
						$$slots: { default: true }
					}),
					'component',
					StorageProviderSubPage,
					244,
					1,
					{ componentTag: 'Panel' }
				);

				var node_9 = sibling(node_5, 2);

				{
					var consequent_12 = ($$anchor) => {
						add_svelte_meta(
							() => Panel($$anchor, {
								get heading() {
									return get(saveCredentialsTitle);
								},
								multi: true,
								children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
									add_svelte_meta(
										() => PanelRow($$anchor, {
											class: 'body flex-column access-keys',
											children: wrap_snippet(StorageProviderSubPage, ($$anchor, $$slotProps) => {
												var fragment_21 = comment();
												var node_10 = first_child(fragment_21);

												{
													var consequent_7 = ($$anchor) => {
														add_svelte_meta(
															() => AccessKeysDefine($$anchor, {
																get provider() {
																	return get(storageProvider);
																}
															}),
															'component',
															StorageProviderSubPage,
															281,
															5,
															{ componentTag: 'AccessKeysDefine' }
														);
													};

													var consequent_8 = ($$anchor) => {
														add_svelte_meta(
															() => KeyFileDefine($$anchor, {
																get provider() {
																	return get(storageProvider);
																}
															}),
															'component',
															StorageProviderSubPage,
															283,
															5,
															{ componentTag: 'KeyFileDefine' }
														);
													};

													var consequent_9 = ($$anchor) => {
														add_svelte_meta(
															() => UseServerRolesDefine($$anchor, {
																get provider() {
																	return get(storageProvider);
																}
															}),
															'component',
															StorageProviderSubPage,
															285,
															5,
															{ componentTag: 'UseServerRolesDefine' }
														);
													};

													var consequent_10 = ($$anchor) => {
														add_svelte_meta(
															() => AccessKeysEntry($$anchor, {
																get provider() {
																	return get(storageProvider);
																},

																get disabled() {
																	return get(authDisabled);
																},

																get accessKeyId() {
																	return get(accessKeyId);
																},

																set accessKeyId($$value) {
																	set(accessKeyId, $$value, true);
																},

																get secretAccessKey() {
																	return get(secretAccessKey);
																},

																set secretAccessKey($$value) {
																	set(secretAccessKey, $$value, true);
																}
															}),
															'component',
															StorageProviderSubPage,
															287,
															5,
															{ componentTag: 'AccessKeysEntry' }
														);
													};

													var consequent_11 = ($$anchor) => {
														add_svelte_meta(
															() => KeyFileEntry($$anchor, {
																get provider() {
																	return get(storageProvider);
																},

																get value() {
																	return get(keyFile);
																},

																set value($$value) {
																	set(keyFile, $$value, true);
																}
															}),
															'component',
															StorageProviderSubPage,
															294,
															5,
															{ componentTag: 'KeyFileEntry' }
														);
													};

													add_svelte_meta(
														() => if_block(node_10, ($$render) => {
															if (strict_equals(get(authMethod), "define") && get(storageProvider).use_access_keys_allowed) $$render(consequent_7); else if (strict_equals(get(authMethod), "define") && get(storageProvider).use_key_file_allowed) $$render(consequent_8, 1); else if (strict_equals(get(authMethod), "server-role") && get(storageProvider).use_server_roles_allowed) $$render(consequent_9, 2); else if (strict_equals(get(authMethod), "database") && get(storageProvider).use_access_keys_allowed) $$render(consequent_10, 3); else if (strict_equals(get(authMethod), "database") && get(storageProvider).use_key_file_allowed) $$render(consequent_11, 4);
														}),
														'if',
														StorageProviderSubPage,
														280,
														4
													);
												}

												append($$anchor, fragment_21);
											}),
											$$slots: { default: true }
										}),
										'component',
										StorageProviderSubPage,
										279,
										3,
										{ componentTag: 'PanelRow' }
									);
								}),
								$$slots: { default: true }
							}),
							'component',
							StorageProviderSubPage,
							278,
							2,
							{ componentTag: 'Panel' }
						);
					};

					add_svelte_meta(
						() => if_block(node_9, ($$render) => {
							if (!get(authDefined)) $$render(consequent_12);
						}),
						'if',
						StorageProviderSubPage,
						277,
						1
					);
				}

				var node_11 = sibling(node_9, 2);

				{
					let $0 = user_derived(() => $needs_refresh() || $settingsLocked());

					add_svelte_meta(
						() => BackNextButtonsRow(node_11, {
							onNext: handleNext,
							get nextDisabled() {
								return get($0);
							},

							get nextText() {
								return $strings().save_and_continue;
							}
						}),
						'component',
						StorageProviderSubPage,
						300,
						1,
						{ componentTag: 'BackNextButtonsRow' }
					);
				}

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		StorageProviderSubPage,
		216,
		0,
		{ componentTag: 'SubPage' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

/**
 * A simple action to scroll the element into view if active.
 *
 * @param {Object} node
 * @param {boolean} active
 */
function scrollIntoView( node, active ) {
	if ( active ) {
		node.scrollIntoView( { behavior: "smooth", block: "center", inline: "nearest" } );
	}
}

Loading[FILENAME] = 'ui/components/Loading.svelte';

var root$a = add_locations(from_html(`<p> </p>`), Loading[FILENAME], [[5, 0]]);

function Loading($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, Loading);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();
	var $$exports = { ...legacy_api() };

	init();

	var p = root$a();
	var text = child(p);
	template_effect(() => set_text(text, $strings().loading));
	append($$anchor, p);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

BucketSettingsSubPage[FILENAME] = 'ui/components/BucketSettingsSubPage.svelte';

var root_3$3 = add_locations(from_html(`<!> <!>`, 1), BucketSettingsSubPage[FILENAME], []);
var root_11 = add_locations(from_html(`<option> </option>`), BucketSettingsSubPage[FILENAME], [[328, 10]]);
var root_10 = add_locations(from_html(`<div class="region flex-column"><label class="input-label" for="region"> <!></label> <select name="region" id="region"></select></div>`), BucketSettingsSubPage[FILENAME], [[322, 7, [[323, 8], [326, 8]]]]);
var root_9$2 = add_locations(from_html(`<div class="flex-row align-center row"><div class="new-bucket-details flex-column"><label class="input-label" for="bucket-name"> </label> <input type="text" id="bucket-name" name="bucket" minlength="3"/></div> <!></div>`), BucketSettingsSubPage[FILENAME], [[306, 5, [[307, 6, [[308, 7], [309, 7]]]]]]);
var root_14$1 = add_locations(from_html(`<option> </option>`), BucketSettingsSubPage[FILENAME], [[348, 8]]);
var root_13$1 = add_locations(from_html(`<label class="input-label" for="list-region"> <!></label> <select name="region" id="list-region"></select>`, 1), BucketSettingsSubPage[FILENAME], [[343, 6], [346, 6]]);
var root_18$1 = add_locations(from_html(`<img class="icon status" type="image/svg+xml"/>`), BucketSettingsSubPage[FILENAME], [[376, 11]]);
var root_17$1 = add_locations(from_html(`<li><img class="icon bucket"/> <p> </p> <!></li>`), BucketSettingsSubPage[FILENAME], [[366, 9, [[373, 10], [374, 10]]]]);
var root_19$1 = add_locations(from_html(`<li class="row nothing-found"><p> </p></li>`), BucketSettingsSubPage[FILENAME], [[381, 8, [[382, 9]]]]);
var root_15$1 = add_locations(from_html(`<ul class="bucket-list"><!></ul>`), BucketSettingsSubPage[FILENAME], [[360, 6]]);
var root_12$2 = add_locations(from_html(`<!> <!>`, 1), BucketSettingsSubPage[FILENAME], []);
var root_21 = add_locations(from_html(`<p class="input-error"> </p>`), BucketSettingsSubPage[FILENAME], [[389, 5]]);
var root_6$2 = add_locations(from_html(`<div class="flex-row align-center row radio-btns"><!> <!></div> <!> <!> <!>`, 1), BucketSettingsSubPage[FILENAME], [[300, 4]]);
var root_25 = add_locations(from_html(`<option> </option>`), BucketSettingsSubPage[FILENAME], [[419, 8]]);
var root_26 = add_locations(from_html(`<p class="input-error"> </p>`), BucketSettingsSubPage[FILENAME], [[430, 5]]);

var root_24 = add_locations(from_html(`<div class="flex-row align-center row"><div class="new-bucket-details flex-column"><label class="input-label" for="new-bucket-name"> </label> <input type="text" id="new-bucket-name" name="bucket" minlength="3"/></div> <div class="region flex-column"><label class="input-label" for="new-region"> <!></label> <select name="region" id="new-region"></select></div></div> <!>`, 1), BucketSettingsSubPage[FILENAME], [
	[
		398,
		4,
		[
			[399, 5, [[400, 6], [401, 6]]],
			[413, 5, [[414, 6], [417, 6]]]
		]
	]
]);

var root_1$a = add_locations(from_html(`<!> <!> <!> <!>`, 1), BucketSettingsSubPage[FILENAME], []);

function BucketSettingsSubPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, BucketSettingsSubPage);

	const $current_settings = () => (
		validate_store(current_settings, 'current_settings'),
		store_get(current_settings, '$current_settings', $$stores)
	);

	const $defined_settings = () => (
		validate_store(defined_settings, 'defined_settings'),
		store_get(defined_settings, '$defined_settings', $$stores)
	);

	const $needs_refresh = () => (
		validate_store(needs_refresh, 'needs_refresh'),
		store_get(needs_refresh, '$needs_refresh', $$stores)
	);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const $settingsLocked = () => (
		validate_store(get(settingsLocked), 'settingsLocked'),
		store_get(get(settingsLocked), '$settingsLocked', $$stores)
	);

	const $storage_provider = () => (
		validate_store(storage_provider, 'storage_provider'),
		store_get(storage_provider, '$storage_provider', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	// Parent page may want to be locked.
	let settingsLocked = tag(state(proxy(writable(false))), 'settingsLocked');

	if (hasContext("settingsLocked")) {
		store_unsub(set(settingsLocked, getContext("settingsLocked"), true), '$settingsLocked', $$stores);
	}

	// Keep track of where we were at prior to any changes made here.
	let initialSettings = $current_settings();

	if (hasContext("initialSettings")) {
		initialSettings = getContext("initialSettings");
	}

	// As this page does not directly alter the settings store until done,
	// we need to keep track of any changes made elsewhere and prompt
	// the user to refresh the page.
	let saving = tag(state(false), 'saving');

	const previousSettings = { ...$current_settings() };
	const previousDefines = { ...$defined_settings() };

	user_pre_effect(() => {
		store_set(needs_refresh, $needs_refresh() || needsRefresh(get(saving), previousSettings, $current_settings(), previousDefines, $defined_settings()));
	});

	let bucketSource = tag(state("existing"), 'bucketSource');
	let enterOrSelectExisting = tag(state("enter"), 'enterOrSelectExisting');

	// If $defined_settings.bucket set, must use it, and disable change.
	let newBucket = tag(state(proxy($settings().bucket)), 'newBucket');

	let defined = tag(user_derived(() => $defined_settings().includes("bucket")), 'defined');
	let disabled = tag(user_derived(() => get(defined) || $needs_refresh() || $settingsLocked()), 'disabled');

	// If $defined_settings.region set, must use it, and disable change.
	let newRegion = tag(state(proxy($settings().region)), 'newRegion');

	let newRegionRequired = tag_proxy(proxy($storage_provider().region_required), 'newRegionRequired');
	let newRegionDefined = tag(user_derived(() => $defined_settings().includes("region")), 'newRegionDefined');
	let newRegionDisabled = tag(user_derived(() => get(newRegionDefined) || $needs_refresh() || $settingsLocked()), 'newRegionDisabled');

	/**
	 * Handles clicking the Existing radio button.
	 *
	 * @property {Event} [event]
	 */
	function handleExisting(event) {
		event.preventDefault();

		if (get(disabled)) {
			return;
		}

		set(bucketSource, "existing");
	}

	/**
	 * Handles clicking the New radio button.
	 *
	 * @property {Event} [event]
	 */
	function handleNew(event) {
		event.preventDefault();

		if (get(disabled)) {
			return;
		}

		set(bucketSource, "new");
	}

	/**
	 * Calls the API to get a list of existing buckets for the currently selected
	 * storage provider and region (if applicable).
	 *
	 * NOTE: This is used from an #await, which is an effect in Svelte 5,
	 *       and so should not access a store that might be updated dynamically,
	 *       otherwise the #await will be reanalyzed every time the store is updated.
	 *
	 * @param {string} region
	 *
	 * @return {Promise<*[]>}
	 */
	async function getBuckets(region) {
		let params = {};

		if (newRegionRequired) {
			params = { region };
		}

		let data = (await track_reactivity_loss(api.get("buckets", params)))();

		if (data.hasOwnProperty("buckets")) {
			if (strict_equals(data.buckets.filter((bucket) => strict_equals(bucket.Name, get(newBucket))).length, 0)) {
				set(newBucket, "");
			}

			return data.buckets;
		}

		set(newBucket, "");

		return [];
	}

	/**
	 * Calls the API to create a new bucket with the currently entered name and selected region.
	 *
	 * @return {Promise<boolean>}
	 */
	async function createBucket() {
		let data = (await track_reactivity_loss(api.post("buckets", { bucket: get(newBucket), region: get(newRegion) })))();

		if (data.hasOwnProperty("saved")) {
			return data.saved;
		}

		return false;
	}

	/**
	 * Potentially returns a reason that the provided bucket name is invalid.
	 *
	 * @param {string} bucket
	 * @param {string} source Either "existing" or "new".
	 * @param {string} existingType Either "enter" or "select".
	 *
	 * @return {string}
	 */
	function getInvalidBucketNameMessage(bucket, source, existingType) {
		// If there's an invalid region defined, don't even bother looking at bucket name.
		if (get(newRegionDefined) && (strict_equals(get(newRegion).length, 0) || !$storage_provider().regions.hasOwnProperty(get(newRegion)))) {
			return $strings().defined_region_invalid;
		}

		const bucketNamePattern = strict_equals(source, "new") ? /[^a-z0-9.\-]/ : /[^a-zA-Z0-9.\-_]/;
		let message = "";

		if (bucket.trim().length < 1) {
			if (strict_equals(source, "existing") && strict_equals(existingType, "select")) {
				message = $strings().no_bucket_selected;
			} else {
				message = $strings().create_bucket_name_missing;
			}
		} else if (strict_equals(true, bucketNamePattern.test(bucket))) {
			message = strict_equals(source, "new")
				? $strings().create_bucket_invalid_chars
				: $strings().select_bucket_invalid_chars;
		} else if (bucket.length < 3) {
			message = $strings().create_bucket_name_short;
		} else if (bucket.length > 63) {
			message = $strings().create_bucket_name_long;
		}

		return message;
	}

	let invalidBucketNameMessage = tag(user_derived(() => getInvalidBucketNameMessage(get(newBucket), get(bucketSource), get(enterOrSelectExisting))), 'invalidBucketNameMessage');

	/**
	 * Returns text to be used on Next button.
	 *
	 * @param {string} source Either "existing" or "new".
	 * @param {string} existingType Either "enter" or "select".
	 *
	 * @return {string}
	 */
	function getNextText(source, existingType) {
		if (strict_equals(source, "existing") && strict_equals(existingType, "enter")) {
			return $strings().save_enter_bucket;
		}

		if (strict_equals(source, "existing") && strict_equals(existingType, "select")) {
			return $strings().save_select_bucket;
		}

		if (strict_equals(source, "new")) {
			return $strings().save_new_bucket;
		}

		return $strings().next;
	}

	let nextText = tag(user_derived(() => getNextText(get(bucketSource), get(enterOrSelectExisting))), 'nextText');

	/**
	 * Handles a Next button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleNext() {
		if (strict_equals(get(bucketSource), "new") && strict_equals(false, (await track_reactivity_loss(createBucket()))())) {
			scrollNotificationsIntoView();

			return;
		}

		set(saving, true);
		appState.pausePeriodicFetch();
		store_mutate(settings, untrack($settings).bucket = get(newBucket), untrack($settings));
		store_mutate(settings, untrack($settings).region = get(newRegion), untrack($settings));

		const result = (await track_reactivity_loss(settings.save()))();

		// If something went wrong, don't move onto next step.
		if (result.hasOwnProperty("saved") && !result.saved) {
			settings.reset();
			set(saving, false);
			(await track_reactivity_loss(appState.resumePeriodicFetch()))();
			scrollNotificationsIntoView();

			return;
		}

		store_set(revalidatingSettings, true);

		const statePromise = appState.resumePeriodicFetch();

		result.bucketSource = get(bucketSource);
		result.initialSettings = initialSettings;
		$$props.onRouteEvent({ event: "settings.save", data: result, default: "/" });

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		(await track_reactivity_loss(statePromise))();

		store_set(revalidatingSettings, false);
	}

	onMount(() => {
		// Default to first region in storage provider if not defined and not set or not valid.
		if (!get(newRegionDefined) && (strict_equals(get(newRegion).length, 0) || !$storage_provider().regions.hasOwnProperty(get(newRegion)))) {
			set(newRegion, Object.keys($storage_provider().regions)[0], true);
		}
	});

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => SubPage($$anchor, {
			name: 'bucket-settings',
			route: '/storage/bucket',
			children: wrap_snippet(BucketSettingsSubPage, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$a();
				var node = first_child(fragment_1);

				add_svelte_meta(
					() => Panel(node, {
						get heading() {
							return $strings().bucket_source_title;
						},
						multi: true,
						get defined() {
							return get(defined);
						},

						children: wrap_snippet(BucketSettingsSubPage, ($$anchor, $$slotProps) => {
							add_svelte_meta(
								() => PanelRow($$anchor, {
									class: 'body flex-row tab-buttons',
									children: wrap_snippet(BucketSettingsSubPage, ($$anchor, $$slotProps) => {
										var fragment_3 = root_3$3();
										var node_1 = first_child(fragment_3);

										{
											let $0 = user_derived(() => strict_equals(get(bucketSource), "existing"));

											add_svelte_meta(
												() => TabButton(node_1, {
													get active() {
														return get($0);
													},

													get disabled() {
														return get(disabled);
													},

													get text() {
														return $strings().use_existing_bucket;
													},
													onclick: handleExisting
												}),
												'component',
												BucketSettingsSubPage,
												282,
												3,
												{ componentTag: 'TabButton' }
											);
										}

										var node_2 = sibling(node_1, 2);

										{
											let $0 = user_derived(() => strict_equals(get(bucketSource), "new"));

											add_svelte_meta(
												() => TabButton(node_2, {
													get active() {
														return get($0);
													},

													get disabled() {
														return get(disabled);
													},

													get text() {
														return $strings().create_new_bucket;
													},
													onclick: handleNew
												}),
												'component',
												BucketSettingsSubPage,
												288,
												3,
												{ componentTag: 'TabButton' }
											);
										}

										append($$anchor, fragment_3);
									}),
									$$slots: { default: true }
								}),
								'component',
								BucketSettingsSubPage,
								281,
								2,
								{ componentTag: 'PanelRow' }
							);
						}),
						$$slots: { default: true }
					}),
					'component',
					BucketSettingsSubPage,
					280,
					1,
					{ componentTag: 'Panel' }
				);

				var node_3 = sibling(node, 2);

				{
					var consequent_7 = ($$anchor) => {
						add_svelte_meta(
							() => Panel($$anchor, {
								get heading() {
									return $strings().existing_bucket_title;
								},

								get storageProvider() {
									return $storage_provider();
								},
								multi: true,
								get defined() {
									return get(defined);
								},

								children: wrap_snippet(BucketSettingsSubPage, ($$anchor, $$slotProps) => {
									add_svelte_meta(
										() => PanelRow($$anchor, {
											class: 'body flex-column',
											children: wrap_snippet(BucketSettingsSubPage, ($$anchor, $$slotProps) => {
												var fragment_6 = root_6$2();
												var div = first_child(fragment_6);
												var node_4 = child(div);

												add_svelte_meta(
													() => RadioButton(node_4, {
														value: 'enter',
														list: true,
														get disabled() {
															return get(disabled);
														},

														get selected() {
															return get(enterOrSelectExisting);
														},

														set selected($$value) {
															set(enterOrSelectExisting, $$value, true);
														},

														children: wrap_snippet(BucketSettingsSubPage, ($$anchor, $$slotProps) => {
															next();

															var text$1 = text();

															template_effect(() => set_text(text$1, $strings().enter_bucket));
															append($$anchor, text$1);
														}),
														$$slots: { default: true }
													}),
													'component',
													BucketSettingsSubPage,
													301,
													5,
													{ componentTag: 'RadioButton' }
												);

												var node_5 = sibling(node_4, 2);

												add_svelte_meta(
													() => RadioButton(node_5, {
														value: 'select',
														list: true,
														get disabled() {
															return get(disabled);
														},

														get selected() {
															return get(enterOrSelectExisting);
														},

														set selected($$value) {
															set(enterOrSelectExisting, $$value, true);
														},

														children: wrap_snippet(BucketSettingsSubPage, ($$anchor, $$slotProps) => {
															next();

															var text_1 = text();

															template_effect(() => set_text(text_1, $strings().select_bucket));
															append($$anchor, text_1);
														}),
														$$slots: { default: true }
													}),
													'component',
													BucketSettingsSubPage,
													302,
													5,
													{ componentTag: 'RadioButton' }
												);

												reset(div);

												var node_6 = sibling(div, 2);

												{
													var consequent_1 = ($$anchor) => {
														var div_1 = root_9$2();
														var div_2 = child(div_1);
														var label = child(div_2);
														var text_2 = child(label, true);

														reset(label);

														var input = sibling(label, 2);

														remove_input_defaults(input);

														let classes;

														reset(div_2);

														var node_7 = sibling(div_2, 2);

														{
															var consequent = ($$anchor) => {
																var div_3 = root_10();
																var label_1 = child(div_3);
																var text_3 = child(label_1);
																var node_8 = sibling(text_3);

																add_svelte_meta(
																	() => DefinedInWPConfig(node_8, {
																		get defined() {
																			return get(newRegionDefined);
																		}
																	}),
																	'component',
																	BucketSettingsSubPage,
																	324,
																	32,
																	{ componentTag: 'DefinedInWPConfig' }
																);

																reset(label_1);

																var select = sibling(label_1, 2);
																let classes_1;

																add_svelte_meta(
																	() => each(select, 5, () => Object.entries($storage_provider().regions), index, ($$anchor, $$item) => {
																		var $$array = user_derived(() => to_array(get($$item), 2));
																		let regionKey = () => get($$array)[0];

																		regionKey();

																		let regionName = () => get($$array)[1];

																		regionName();

																		var option = root_11();
																		var text_4 = child(option, true);

																		reset(option);

																		var option_value = {};

																		template_effect(() => {
																			set_selected(option, strict_equals(regionKey(), get(newRegion)));
																			set_text(text_4, regionName());

																			if (option_value !== (option_value = regionKey())) {
																				option.value = (option.__value = regionKey()) ?? '';
																			}
																		});

																		append($$anchor, option);
																	}),
																	'each',
																	BucketSettingsSubPage,
																	327,
																	9
																);

																reset(select);
																reset(div_3);

																template_effect(() => {
																	set_text(text_3, `${$strings().region ?? ''} `);
																	select.disabled = get(newRegionDisabled);
																	classes_1 = set_class(select, 1, '', null, classes_1, { disabled: get(newRegionDisabled) });
																});

																bind_select_value(
																	select,
																	function get$1() {
																		return get(newRegion);
																	},
																	function set$1($$value) {
																		set(newRegion, $$value);
																	}
																);

																append($$anchor, div_3);
															};

															add_svelte_meta(
																() => if_block(node_7, ($$render) => {
																	if (newRegionRequired) $$render(consequent);
																}),
																'if',
																BucketSettingsSubPage,
																321,
																6
															);
														}

														reset(div_1);

														template_effect(() => {
															set_text(text_2, $strings().bucket_name);
															classes = set_class(input, 1, 'bucket-name', null, classes, { disabled: get(disabled) });
															set_attribute(input, 'placeholder', $strings().enter_bucket_name_placeholder);
															input.disabled = get(disabled);
														});

														bind_value(
															input,
															function get$1() {
																return get(newBucket);
															},
															function set$1($$value) {
																set(newBucket, $$value);
															}
														);

														append($$anchor, div_1);
													};

													add_svelte_meta(
														() => if_block(node_6, ($$render) => {
															if (strict_equals(get(enterOrSelectExisting), "enter")) $$render(consequent_1);
														}),
														'if',
														BucketSettingsSubPage,
														305,
														4
													);
												}

												var node_9 = sibling(node_6, 2);

												{
													var consequent_5 = ($$anchor) => {
														var fragment_9 = root_12$2();
														var node_10 = first_child(fragment_9);

														{
															var consequent_2 = ($$anchor) => {
																var fragment_10 = root_13$1();
																var label_2 = first_child(fragment_10);
																var text_5 = child(label_2);
																var node_11 = sibling(text_5);

																add_svelte_meta(
																	() => DefinedInWPConfig(node_11, {
																		get defined() {
																			return get(newRegionDefined);
																		}
																	}),
																	'component',
																	BucketSettingsSubPage,
																	344,
																	30,
																	{ componentTag: 'DefinedInWPConfig' }
																);

																reset(label_2);

																var select_1 = sibling(label_2, 2);
																let classes_2;

																add_svelte_meta(
																	() => each(select_1, 5, () => Object.entries($storage_provider().regions), index, ($$anchor, $$item) => {
																		var $$array_1 = user_derived(() => to_array(get($$item), 2));
																		let regionKey = () => get($$array_1)[0];

																		regionKey();

																		let regionName = () => get($$array_1)[1];

																		regionName();

																		var option_1 = root_14$1();
																		var text_6 = child(option_1, true);

																		reset(option_1);

																		var option_1_value = {};

																		template_effect(() => {
																			set_selected(option_1, strict_equals(regionKey(), get(newRegion)));
																			set_text(text_6, regionName());

																			if (option_1_value !== (option_1_value = regionKey())) {
																				option_1.value = (option_1.__value = regionKey()) ?? '';
																			}
																		});

																		append($$anchor, option_1);
																	}),
																	'each',
																	BucketSettingsSubPage,
																	347,
																	7
																);

																reset(select_1);

																template_effect(() => {
																	set_text(text_5, `${$strings().region ?? ''} `);
																	select_1.disabled = get(newRegionDisabled);
																	classes_2 = set_class(select_1, 1, '', null, classes_2, { disabled: get(newRegionDisabled) });
																});

																bind_select_value(
																	select_1,
																	function get$1() {
																		return get(newRegion);
																	},
																	function set$1($$value) {
																		set(newRegion, $$value);
																	}
																);

																append($$anchor, fragment_10);
															};

															add_svelte_meta(
																() => if_block(node_10, ($$render) => {
																	if (newRegionRequired) $$render(consequent_2);
																}),
																'if',
																BucketSettingsSubPage,
																342,
																5
															);
														}

														var node_12 = sibling(node_10, 2);

														add_svelte_meta(
															() => await_block(
																node_12,
																() => getBuckets(get(newRegion)),
																($$anchor) => {
																	add_svelte_meta(() => Loading($$anchor, {}), 'component', BucketSettingsSubPage, 358, 6, { componentTag: 'Loading' });
																},
																($$anchor, buckets) => {
																	var ul = root_15$1();
																	var node_13 = child(ul);

																	{
																		var consequent_4 = ($$anchor) => {
																			var fragment_11 = comment();
																			var node_14 = first_child(fragment_11);

																			add_svelte_meta(
																				() => each(node_14, 17, () => get(buckets), index, ($$anchor, bucket) => {
																					var li = root_17$1();
																					let classes_3;
																					var img = child(li);
																					var p = sibling(img, 2);
																					var text_7 = child(p, true);

																					reset(p);

																					var node_15 = sibling(p, 2);

																					{
																						var consequent_3 = ($$anchor) => {
																							var img_1 = root_18$1();

																							template_effect(() => {
																								set_attribute(img_1, 'src', $urls().assets + 'img/icon/licence-checked.svg');
																								set_attribute(img_1, 'alt', $strings().selected_desc);
																							});

																							append($$anchor, img_1);
																						};

																						add_svelte_meta(
																							() => if_block(node_15, ($$render) => {
																								if (strict_equals(get(newBucket), get(bucket).Name)) $$render(consequent_3);
																							}),
																							'if',
																							BucketSettingsSubPage,
																							375,
																							10
																						);
																					}

																					reset(li);
																					action(li, ($$node, $$action_arg) => scrollIntoView?.($$node, $$action_arg), () => strict_equals(get(newBucket), get(bucket).Name));

																					template_effect(() => {
																						classes_3 = set_class(li, 1, 'row', null, classes_3, {
																							active: strict_equals(get(newBucket), get(bucket).Name)
																						});

																						set_attribute(li, 'data-bucket-name', get(bucket).Name);
																						set_attribute(img, 'src', $urls().assets + 'img/icon/bucket.svg');
																						set_attribute(img, 'alt', $strings().bucket_icon);
																						set_text(text_7, get(bucket).Name);
																					});

																					delegated('click', li, function click() {
																						return set(newBucket, get(bucket).Name, true);
																					});

																					append($$anchor, li);
																				}),
																				'each',
																				BucketSettingsSubPage,
																				362,
																				8
																			);

																			append($$anchor, fragment_11);
																		};

																		var alternate = ($$anchor) => {
																			var li_1 = root_19$1();
																			var p_1 = child(li_1);
																			var text_8 = child(p_1, true);

																			reset(p_1);
																			reset(li_1);
																			template_effect(() => set_text(text_8, $strings().nothing_found));
																			append($$anchor, li_1);
																		};

																		add_svelte_meta(
																			() => if_block(node_13, ($$render) => {
																				if (get(buckets).length) $$render(consequent_4); else $$render(alternate, -1);
																			}),
																			'if',
																			BucketSettingsSubPage,
																			361,
																			7
																		);
																	}

																	reset(ul);
																	append($$anchor, ul);
																}
															),
															'await',
															BucketSettingsSubPage,
															357,
															5
														);

														append($$anchor, fragment_9);
													};

													add_svelte_meta(
														() => if_block(node_9, ($$render) => {
															if (strict_equals(get(enterOrSelectExisting), "select")) $$render(consequent_5);
														}),
														'if',
														BucketSettingsSubPage,
														341,
														4
													);
												}

												var node_16 = sibling(node_9, 2);

												{
													var consequent_6 = ($$anchor) => {
														var p_2 = root_21();
														var text_9 = child(p_2, true);

														reset(p_2);
														template_effect(() => set_text(text_9, get(invalidBucketNameMessage)));
														transition(3, p_2, () => slide);
														append($$anchor, p_2);
													};

													add_svelte_meta(
														() => if_block(node_16, ($$render) => {
															if (get(invalidBucketNameMessage)) $$render(consequent_6);
														}),
														'if',
														BucketSettingsSubPage,
														388,
														4
													);
												}

												append($$anchor, fragment_6);
											}),
											$$slots: { default: true }
										}),
										'component',
										BucketSettingsSubPage,
										299,
										3,
										{ componentTag: 'PanelRow' }
									);
								}),
								$$slots: { default: true }
							}),
							'component',
							BucketSettingsSubPage,
							298,
							2,
							{ componentTag: 'Panel' }
						);
					};

					add_svelte_meta(
						() => if_block(node_3, ($$render) => {
							if (strict_equals(get(bucketSource), "existing")) $$render(consequent_7);
						}),
						'if',
						BucketSettingsSubPage,
						297,
						1
					);
				}

				var node_17 = sibling(node_3, 2);

				{
					var consequent_9 = ($$anchor) => {
						add_svelte_meta(
							() => Panel($$anchor, {
								get heading() {
									return $strings().new_bucket_title;
								},

								get storageProvider() {
									return $storage_provider();
								},
								multi: true,
								get defined() {
									return get(defined);
								},

								children: wrap_snippet(BucketSettingsSubPage, ($$anchor, $$slotProps) => {
									add_svelte_meta(
										() => PanelRow($$anchor, {
											class: 'body flex-column',
											children: wrap_snippet(BucketSettingsSubPage, ($$anchor, $$slotProps) => {
												var fragment_15 = root_24();
												var div_4 = first_child(fragment_15);
												var div_5 = child(div_4);
												var label_3 = child(div_5);
												var text_10 = child(label_3, true);

												reset(label_3);

												var input_1 = sibling(label_3, 2);

												remove_input_defaults(input_1);

												let classes_4;

												reset(div_5);

												var div_6 = sibling(div_5, 2);
												var label_4 = child(div_6);
												var text_11 = child(label_4);
												var node_18 = sibling(text_11);

												add_svelte_meta(
													() => DefinedInWPConfig(node_18, {
														get defined() {
															return get(newRegionDefined);
														}
													}),
													'component',
													BucketSettingsSubPage,
													415,
													30,
													{ componentTag: 'DefinedInWPConfig' }
												);

												reset(label_4);

												var select_2 = sibling(label_4, 2);
												let classes_5;

												add_svelte_meta(
													() => each(select_2, 5, () => Object.entries($storage_provider().regions), index, ($$anchor, $$item) => {
														var $$array_2 = user_derived(() => to_array(get($$item), 2));
														let regionKey = () => get($$array_2)[0];

														regionKey();

														let regionName = () => get($$array_2)[1];

														regionName();

														var option_2 = root_25();
														var text_12 = child(option_2, true);

														reset(option_2);

														var option_2_value = {};

														template_effect(() => {
															set_selected(option_2, strict_equals(regionKey(), get(newRegion)));
															set_text(text_12, regionName());

															if (option_2_value !== (option_2_value = regionKey())) {
																option_2.value = (option_2.__value = regionKey()) ?? '';
															}
														});

														append($$anchor, option_2);
													}),
													'each',
													BucketSettingsSubPage,
													418,
													7
												);

												reset(select_2);
												reset(div_6);
												reset(div_4);

												var node_19 = sibling(div_4, 2);

												{
													var consequent_8 = ($$anchor) => {
														var p_3 = root_26();
														var text_13 = child(p_3, true);

														reset(p_3);
														template_effect(() => set_text(text_13, get(invalidBucketNameMessage)));
														transition(3, p_3, () => slide);
														append($$anchor, p_3);
													};

													add_svelte_meta(
														() => if_block(node_19, ($$render) => {
															if (get(invalidBucketNameMessage)) $$render(consequent_8);
														}),
														'if',
														BucketSettingsSubPage,
														429,
														4
													);
												}

												template_effect(() => {
													set_text(text_10, $strings().bucket_name);
													classes_4 = set_class(input_1, 1, 'bucket-name', null, classes_4, { disabled: get(disabled) });
													set_attribute(input_1, 'placeholder', $strings().enter_bucket_name_placeholder);
													input_1.disabled = get(disabled);
													set_text(text_11, `${$strings().region ?? ''} `);
													select_2.disabled = get(newRegionDisabled);
													classes_5 = set_class(select_2, 1, '', null, classes_5, { disabled: get(newRegionDisabled) });
												});

												bind_value(
													input_1,
													function get$1() {
														return get(newBucket);
													},
													function set$1($$value) {
														set(newBucket, $$value);
													}
												);

												bind_select_value(
													select_2,
													function get$1() {
														return get(newRegion);
													},
													function set$1($$value) {
														set(newRegion, $$value);
													}
												);

												append($$anchor, fragment_15);
											}),
											$$slots: { default: true }
										}),
										'component',
										BucketSettingsSubPage,
										397,
										3,
										{ componentTag: 'PanelRow' }
									);
								}),
								$$slots: { default: true }
							}),
							'component',
							BucketSettingsSubPage,
							396,
							2,
							{ componentTag: 'Panel' }
						);
					};

					add_svelte_meta(
						() => if_block(node_17, ($$render) => {
							if (strict_equals(get(bucketSource), "new")) $$render(consequent_9);
						}),
						'if',
						BucketSettingsSubPage,
						395,
						1
					);
				}

				var node_20 = sibling(node_17, 2);

				{
					let $0 = user_derived(() => get(invalidBucketNameMessage) || $needs_refresh() || $settingsLocked());

					add_svelte_meta(
						() => BackNextButtonsRow(node_20, {
							onNext: handleNext,
							get nextText() {
								return get(nextText);
							},

							get nextDisabled() {
								return get($0);
							},

							get nextTitle() {
								return get(invalidBucketNameMessage);
							}
						}),
						'component',
						BucketSettingsSubPage,
						436,
						1,
						{ componentTag: 'BackNextButtonsRow' }
					);
				}

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		BucketSettingsSubPage,
		279,
		0,
		{ componentTag: 'SubPage' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

delegate(['click']);

Checkbox[FILENAME] = 'ui/components/Checkbox.svelte';

var root$9 = add_locations(from_html(`<div><label class="toggle-label"><input type="checkbox"/> <!></label></div>`), Checkbox[FILENAME], [[19, 0, [[20, 1, [[21, 2]]]]]]);

function Checkbox($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Checkbox);

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {boolean} [checked]
	 * @property {boolean} [disabled]
	 * @property {import("svelte").Snippet} [children]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, ""),
		checked = prop($$props, 'checked', 15, false),
		disabled = prop($$props, 'disabled', 3, false);

	var $$exports = { ...legacy_api() };
	var div = root$9();
	let classes;
	var label = child(div);
	var input = child(label);

	var node = sibling(input, 2);

	add_svelte_meta(() => snippet(node, () => $$props.children ?? noop), 'render', Checkbox, 22, 2);

	template_effect(() => {
		classes = set_class(div, 1, 'checkbox', null, classes, { locked: disabled(), disabled: disabled() });
		set_attribute(label, 'for', name());
		set_attribute(input, 'id', name());
		input.disabled = disabled();
	});

	bind_checked(
		input,
		function get() {
			return checked();
		},
		function set($$value) {
			checked($$value);
		}
	);

	append($$anchor, div);

	return pop($$exports);
}

SecuritySubPage[FILENAME] = 'ui/components/SecuritySubPage.svelte';

var root_4$2 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[229, 4], [230, 4]]);
var root_5$2 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[232, 4], [233, 4]]);
var root_6$1 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[235, 4], [236, 4]]);
var root_7$1 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[238, 4], [239, 4]]);
var root_8$2 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[241, 4], [242, 4]]);
var root_9$1 = add_locations(from_html(`<div><!></div>`), SecuritySubPage[FILENAME], [[246, 3]]);
var root_2$5 = add_locations(from_html(`<!> <!>`, 1), SecuritySubPage[FILENAME], []);
var root_14 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[264, 4], [265, 4]]);
var root_15 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[267, 4], [268, 4]]);
var root_16 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[270, 4], [271, 4]]);
var root_17 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[273, 4], [274, 4]]);
var root_18 = add_locations(from_html(`<p></p> <p><!> <!></p>`, 1), SecuritySubPage[FILENAME], [[276, 4], [277, 4]]);
var root_19 = add_locations(from_html(`<div><!></div>`), SecuritySubPage[FILENAME], [[281, 3]]);
var root_12$1 = add_locations(from_html(`<!> <!>`, 1), SecuritySubPage[FILENAME], []);
var root_1$9 = add_locations(from_html(`<!> <!> <!>`, 1), SecuritySubPage[FILENAME], []);

function SecuritySubPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, SecuritySubPage);

	const $current_settings = () => (
		validate_store(current_settings, 'current_settings'),
		store_get(current_settings, '$current_settings', $$stores)
	);

	const $defined_settings = () => (
		validate_store(defined_settings, 'defined_settings'),
		store_get(defined_settings, '$defined_settings', $$stores)
	);

	const $needs_refresh = () => (
		validate_store(needs_refresh, 'needs_refresh'),
		store_get(needs_refresh, '$needs_refresh', $$stores)
	);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $settingsLocked = () => (
		validate_store(get(settingsLocked), 'settingsLocked'),
		store_get(get(settingsLocked), '$settingsLocked', $$stores)
	);

	const $delivery_provider = () => (
		validate_store(delivery_provider, 'delivery_provider'),
		store_get(delivery_provider, '$delivery_provider', $$stores)
	);

	const $storage_provider = () => (
		validate_store(storage_provider, 'storage_provider'),
		store_get(storage_provider, '$storage_provider', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	// Parent page may want to be locked.
	let settingsLocked = tag(state(proxy(writable(false))), 'settingsLocked');

	if (hasContext("settingsLocked")) {
		store_unsub(set(settingsLocked, getContext("settingsLocked"), true), '$settingsLocked', $$stores);
	}

	// As this page does not directly alter the settings store until done,
	// we need to keep track of any changes made elsewhere and prompt
	// the user to refresh the page.
	let saving = tag(state(false), 'saving');

	const previousSettings = { ...$current_settings() };
	const previousDefines = { ...$defined_settings() };

	user_pre_effect(() => {
		store_set(needs_refresh, $needs_refresh() || needsRefresh(get(saving), previousSettings, $current_settings(), previousDefines, $defined_settings()));
	});

	let blockPublicAccess = tag(state(proxy($settings()["block-public-access"])), 'blockPublicAccess');
	let bapaSetupConfirmed = tag(state(false), 'bapaSetupConfirmed');
	let objectOwnershipEnforced = tag(state(proxy($settings()["object-ownership-enforced"])), 'objectOwnershipEnforced');
	let ooeSetupConfirmed = tag(state(false), 'ooeSetupConfirmed');

	// During initial setup we show a slightly different page
	// if ACLs disabled but unsupported by Delivery Provider.
	let initialSetup = tag(state(false), 'initialSetup');

	if (hasContext("initialSetup")) {
		set(initialSetup, getContext("initialSetup"), true);
	}

	// If provider has changed, then still treat as initial setup.
	// svelte-ignore state_referenced_locally
	if (!get(initialSetup) && hasContext("initialSettings") && strict_equals(getContext("initialSettings").provider, $current_settings().provider, false)) {
		set(initialSetup, true);
	}

	/**
	 * Calls API to update the properties of the current bucket.
	 *
	 * @return {Promise<boolean|*>}
	 */
	async function updateBucketProperties() {
		let data = (await track_reactivity_loss(api.put("buckets", {
			bucket: $settings().bucket,
			blockPublicAccess: get(blockPublicAccess),
			objectOwnershipEnforced: get(objectOwnershipEnforced)
		})))();

		if (data.hasOwnProperty("saved")) {
			return data.saved;
		}

		return false;
	}

	/**
	 * Returns text to be displayed on Next button.
	 *
	 * @param {boolean} bapaCurrent
	 * @param {boolean} bapaNew
	 * @param {boolean} ooeCurrent
	 * @param {boolean} ooeNew
	 * @param {boolean} needsRefresh
	 * @param {boolean} settingsLocked
	 *
	 * @return {string}
	 */
	function getNextText(
		bapaCurrent,
		bapaNew,
		ooeCurrent,
		ooeNew,
		needsRefresh,
		settingsLocked
	) {
		if (needsRefresh || settingsLocked) {
			return $strings().settings_locked;
		}

		if (strict_equals(bapaCurrent, bapaNew, false) || strict_equals(ooeCurrent, ooeNew, false)) {
			return $strings().update_bucket_security;
		}

		return $strings().keep_bucket_security;
	}

	let nextText = tag(user_derived(() => getNextText($current_settings()["block-public-access"], get(blockPublicAccess), $current_settings()["object-ownership-enforced"], get(objectOwnershipEnforced), $needs_refresh(), $settingsLocked())), 'nextText');

	/**
	 * Determines whether the Next button should be disabled or not.
	 *
	 * If the delivery provider supports the security setting, then do not enable it until setup confirmed.
	 *
	 * All other scenarios result in safe results or warned against repercussions that are being explicitly ignored.
	 *
	 * @param {boolean} currentValue
	 * @param {boolean} newValue
	 * @param {boolean} supported
	 * @param {boolean} setupConfirmed
	 * @param {boolean} needsRefresh
	 * @param {boolean} settingsLocked
	 *
	 * @returns {boolean}
	 */
	function getNextDisabled(
		currentValue,
		newValue,
		supported,
		setupConfirmed,
		needsRefresh,
		settingsLocked
	) {
		return needsRefresh || settingsLocked || !currentValue && newValue && supported && !setupConfirmed;
	}

	let nextDisabled = tag(user_derived(() => getNextDisabled($current_settings()["block-public-access"], get(blockPublicAccess), $delivery_provider().block_public_access_supported, get(bapaSetupConfirmed), $needs_refresh(), $settingsLocked()) || getNextDisabled($current_settings()["object-ownership-enforced"], get(objectOwnershipEnforced), $delivery_provider().object_ownership_supported, get(ooeSetupConfirmed), $needs_refresh(), $settingsLocked())), 'nextDisabled');

	/**
	 * Handles a Next button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleNext() {
		if (strict_equals(get(blockPublicAccess), $current_settings()["block-public-access"]) && strict_equals(get(objectOwnershipEnforced), $current_settings()["object-ownership-enforced"])) {
			$$props.onRouteEvent({ event: "next", default: "/" });

			return;
		}

		set(saving, true);
		appState.pausePeriodicFetch();

		const result = (await track_reactivity_loss(updateBucketProperties()))();

		// Regardless of whether update succeeded or not, make sure settings are up-to-date.
		(await track_reactivity_loss(settings.fetch()))();

		if (strict_equals(false, result)) {
			set(saving, false);
			(await track_reactivity_loss(appState.resumePeriodicFetch()))();
			scrollNotificationsIntoView();

			return;
		}

		store_set(revalidatingSettings, true);

		const statePromise = appState.resumePeriodicFetch();

		// Block All Public Access changed.
		$$props.onRouteEvent({
			event: "bucket-security",
			data: {
				blockPublicAccess: $settings()["block-public-access"],
				objectOwnershipEnforced: $settings()["object-ownership-enforced"]
			},
			default: "/"
		});

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		(await track_reactivity_loss(statePromise))();

		store_set(revalidatingSettings, false);
	}

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => SubPage($$anchor, {
			name: 'bapa-settings',
			route: '/storage/security',
			children: wrap_snippet(SecuritySubPage, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$9();
				var node = first_child(fragment_1);

				add_svelte_meta(
					() => Panel(node, {
						class: 'toggle-header',
						get heading() {
							return $strings().block_public_access_title;
						},
						toggleName: 'block-public-access',
						helpKey: 'block-public-access',
						multi: true,
						get toggle() {
							return get(blockPublicAccess);
						},

						set toggle($$value) {
							set(blockPublicAccess, $$value, true);
						},

						children: wrap_snippet(SecuritySubPage, ($$anchor, $$slotProps) => {
							var fragment_2 = root_2$5();
							var node_1 = first_child(fragment_2);

							add_svelte_meta(
								() => PanelRow(node_1, {
									class: 'body flex-column',
									children: wrap_snippet(SecuritySubPage, ($$anchor, $$slotProps) => {
										var fragment_3 = comment();
										var node_2 = first_child(fragment_3);

										{
											var consequent = ($$anchor) => {
												var fragment_4 = root_4$2();
												var p = first_child(fragment_4);

												html(p, () => $strings().block_public_access_enabled_setup_sub, true);
												reset(p);

												var p_1 = sibling(p, 2);
												var node_3 = child(p_1);

												html(node_3, () => $delivery_provider().block_public_access_enabled_unsupported_setup_desc);

												var node_4 = sibling(node_3, 2);

												html(node_4, () => $storage_provider().block_public_access_enabled_unsupported_setup_desc);
												reset(p_1);
												append($$anchor, fragment_4);
											};

											var consequent_1 = ($$anchor) => {
												var fragment_5 = root_5$2();
												var p_2 = first_child(fragment_5);

												html(p_2, () => $strings().block_public_access_enabled_sub, true);
												reset(p_2);

												var p_3 = sibling(p_2, 2);
												var node_5 = child(p_3);

												html(node_5, () => $delivery_provider().block_public_access_enabled_supported_desc);

												var node_6 = sibling(node_5, 2);

												html(node_6, () => $storage_provider().block_public_access_enabled_supported_desc);
												reset(p_3);
												append($$anchor, fragment_5);
											};

											var consequent_2 = ($$anchor) => {
												var fragment_6 = root_6$1();
												var p_4 = first_child(fragment_6);

												html(p_4, () => $strings().block_public_access_enabled_sub, true);
												reset(p_4);

												var p_5 = sibling(p_4, 2);
												var node_7 = child(p_5);

												html(node_7, () => $delivery_provider().block_public_access_enabled_unsupported_desc);

												var node_8 = sibling(node_7, 2);

												html(node_8, () => $storage_provider().block_public_access_enabled_unsupported_desc);
												reset(p_5);
												append($$anchor, fragment_6);
											};

											var consequent_3 = ($$anchor) => {
												var fragment_7 = root_7$1();
												var p_6 = first_child(fragment_7);

												html(p_6, () => $strings().block_public_access_disabled_sub, true);
												reset(p_6);

												var p_7 = sibling(p_6, 2);
												var node_9 = child(p_7);

												html(node_9, () => $delivery_provider().block_public_access_disabled_supported_desc);

												var node_10 = sibling(node_9, 2);

												html(node_10, () => $storage_provider().block_public_access_disabled_supported_desc);
												reset(p_7);
												append($$anchor, fragment_7);
											};

											var alternate = ($$anchor) => {
												var fragment_8 = root_8$2();
												var p_8 = first_child(fragment_8);

												html(p_8, () => $strings().block_public_access_disabled_sub, true);
												reset(p_8);

												var p_9 = sibling(p_8, 2);
												var node_11 = child(p_9);

												html(node_11, () => $delivery_provider().block_public_access_disabled_unsupported_desc);

												var node_12 = sibling(node_11, 2);

												html(node_12, () => $storage_provider().block_public_access_disabled_unsupported_desc);
												reset(p_9);
												append($$anchor, fragment_8);
											};

											add_svelte_meta(
												() => if_block(node_2, ($$render) => {
													if (get(initialSetup) && $current_settings()["block-public-access"] && !$delivery_provider().block_public_access_supported) $$render(consequent); else if ($current_settings()["block-public-access"] && $delivery_provider().block_public_access_supported) $$render(consequent_1, 1); else if ($current_settings()["block-public-access"] && !$delivery_provider().block_public_access_supported) $$render(consequent_2, 2); else if (!$current_settings()["block-public-access"] && $delivery_provider().block_public_access_supported) $$render(consequent_3, 3); else $$render(alternate, -1);
												}),
												'if',
												SecuritySubPage,
												228,
												3
											);
										}

										append($$anchor, fragment_3);
									}),
									$$slots: { default: true }
								}),
								'component',
								SecuritySubPage,
								227,
								2,
								{ componentTag: 'PanelRow' }
							);

							var node_13 = sibling(node_1, 2);

							{
								var consequent_4 = ($$anchor) => {
									var div = root_9$1();
									var node_14 = child(div);

									add_svelte_meta(
										() => PanelRow(node_14, {
											class: 'body flex-column toggle-reveal',
											footer: true,
											children: wrap_snippet(SecuritySubPage, ($$anchor, $$slotProps) => {
												{
													let $0 = user_derived(() => $needs_refresh() || $settingsLocked());

													add_svelte_meta(
														() => Checkbox($$anchor, {
															name: 'confirm-setup-bapa-oai',
															get disabled() {
																return get($0);
															},

															get checked() {
																return get(bapaSetupConfirmed);
															},

															set checked($$value) {
																set(bapaSetupConfirmed, $$value, true);
															},

															children: wrap_snippet(SecuritySubPage, ($$anchor, $$slotProps) => {
																var fragment_10 = comment();
																var node_15 = first_child(fragment_10);

																html(node_15, () => $delivery_provider().block_public_access_confirm_setup_prompt);
																append($$anchor, fragment_10);
															}),
															$$slots: { default: true }
														}),
														'component',
														SecuritySubPage,
														248,
														5,
														{ componentTag: 'Checkbox' }
													);
												}
											}),
											$$slots: { default: true }
										}),
										'component',
										SecuritySubPage,
										247,
										4,
										{ componentTag: 'PanelRow' }
									);

									reset(div);
									transition(3, div, () => slide);
									append($$anchor, div);
								};

								add_svelte_meta(
									() => if_block(node_13, ($$render) => {
										if (!$current_settings()["block-public-access"] && get(blockPublicAccess) && $delivery_provider().block_public_access_supported) $$render(consequent_4);
									}),
									'if',
									SecuritySubPage,
									245,
									2
								);
							}

							append($$anchor, fragment_2);
						}),
						$$slots: { default: true }
					}),
					'component',
					SecuritySubPage,
					219,
					1,
					{ componentTag: 'Panel' }
				);

				var node_16 = sibling(node, 2);

				add_svelte_meta(
					() => Panel(node_16, {
						class: 'toggle-header',
						get heading() {
							return $strings().object_ownership_title;
						},
						toggleName: 'object-ownership-enforced',
						helpKey: 'object-ownership-enforced',
						multi: true,
						get toggle() {
							return get(objectOwnershipEnforced);
						},

						set toggle($$value) {
							set(objectOwnershipEnforced, $$value, true);
						},

						children: wrap_snippet(SecuritySubPage, ($$anchor, $$slotProps) => {
							var fragment_11 = root_12$1();
							var node_17 = first_child(fragment_11);

							add_svelte_meta(
								() => PanelRow(node_17, {
									class: 'body flex-column',
									children: wrap_snippet(SecuritySubPage, ($$anchor, $$slotProps) => {
										var fragment_12 = comment();
										var node_18 = first_child(fragment_12);

										{
											var consequent_5 = ($$anchor) => {
												var fragment_13 = root_14();
												var p_10 = first_child(fragment_13);

												html(p_10, () => $strings().object_ownership_enforced_setup_sub, true);
												reset(p_10);

												var p_11 = sibling(p_10, 2);
												var node_19 = child(p_11);

												html(node_19, () => $delivery_provider().object_ownership_enforced_unsupported_setup_desc);

												var node_20 = sibling(node_19, 2);

												html(node_20, () => $storage_provider().object_ownership_enforced_unsupported_setup_desc);
												reset(p_11);
												append($$anchor, fragment_13);
											};

											var consequent_6 = ($$anchor) => {
												var fragment_14 = root_15();
												var p_12 = first_child(fragment_14);

												html(p_12, () => $strings().object_ownership_enforced_sub, true);
												reset(p_12);

												var p_13 = sibling(p_12, 2);
												var node_21 = child(p_13);

												html(node_21, () => $delivery_provider().object_ownership_enforced_supported_desc);

												var node_22 = sibling(node_21, 2);

												html(node_22, () => $storage_provider().object_ownership_enforced_supported_desc);
												reset(p_13);
												append($$anchor, fragment_14);
											};

											var consequent_7 = ($$anchor) => {
												var fragment_15 = root_16();
												var p_14 = first_child(fragment_15);

												html(p_14, () => $strings().object_ownership_enforced_sub, true);
												reset(p_14);

												var p_15 = sibling(p_14, 2);
												var node_23 = child(p_15);

												html(node_23, () => $delivery_provider().object_ownership_enforced_unsupported_desc);

												var node_24 = sibling(node_23, 2);

												html(node_24, () => $storage_provider().object_ownership_enforced_unsupported_desc);
												reset(p_15);
												append($$anchor, fragment_15);
											};

											var consequent_8 = ($$anchor) => {
												var fragment_16 = root_17();
												var p_16 = first_child(fragment_16);

												html(p_16, () => $strings().object_ownership_not_enforced_sub, true);
												reset(p_16);

												var p_17 = sibling(p_16, 2);
												var node_25 = child(p_17);

												html(node_25, () => $delivery_provider().object_ownership_not_enforced_supported_desc);

												var node_26 = sibling(node_25, 2);

												html(node_26, () => $storage_provider().object_ownership_not_enforced_supported_desc);
												reset(p_17);
												append($$anchor, fragment_16);
											};

											var alternate_1 = ($$anchor) => {
												var fragment_17 = root_18();
												var p_18 = first_child(fragment_17);

												html(p_18, () => $strings().object_ownership_not_enforced_sub, true);
												reset(p_18);

												var p_19 = sibling(p_18, 2);
												var node_27 = child(p_19);

												html(node_27, () => $delivery_provider().object_ownership_not_enforced_unsupported_desc);

												var node_28 = sibling(node_27, 2);

												html(node_28, () => $storage_provider().object_ownership_not_enforced_unsupported_desc);
												reset(p_19);
												append($$anchor, fragment_17);
											};

											add_svelte_meta(
												() => if_block(node_18, ($$render) => {
													if (get(initialSetup) && $current_settings()["object-ownership-enforced"] && !$delivery_provider().object_ownership_supported) $$render(consequent_5); else if ($current_settings()["object-ownership-enforced"] && $delivery_provider().object_ownership_supported) $$render(consequent_6, 1); else if ($current_settings()["object-ownership-enforced"] && !$delivery_provider().object_ownership_supported) $$render(consequent_7, 2); else if (!$current_settings()["object-ownership-enforced"] && $delivery_provider().object_ownership_supported) $$render(consequent_8, 3); else $$render(alternate_1, -1);
												}),
												'if',
												SecuritySubPage,
												263,
												3
											);
										}

										append($$anchor, fragment_12);
									}),
									$$slots: { default: true }
								}),
								'component',
								SecuritySubPage,
								262,
								2,
								{ componentTag: 'PanelRow' }
							);

							var node_29 = sibling(node_17, 2);

							{
								var consequent_9 = ($$anchor) => {
									var div_1 = root_19();
									var node_30 = child(div_1);

									add_svelte_meta(
										() => PanelRow(node_30, {
											class: 'body flex-column toggle-reveal',
											children: wrap_snippet(SecuritySubPage, ($$anchor, $$slotProps) => {
												{
													let $0 = user_derived(() => $needs_refresh() || $settingsLocked());

													add_svelte_meta(
														() => Checkbox($$anchor, {
															name: 'confirm-setup-ooe-oai',
															get disabled() {
																return get($0);
															},

															get checked() {
																return get(ooeSetupConfirmed);
															},

															set checked($$value) {
																set(ooeSetupConfirmed, $$value, true);
															},

															children: wrap_snippet(SecuritySubPage, ($$anchor, $$slotProps) => {
																var fragment_19 = comment();
																var node_31 = first_child(fragment_19);

																html(node_31, () => $delivery_provider().object_ownership_confirm_setup_prompt);
																append($$anchor, fragment_19);
															}),
															$$slots: { default: true }
														}),
														'component',
														SecuritySubPage,
														283,
														5,
														{ componentTag: 'Checkbox' }
													);
												}
											}),
											$$slots: { default: true }
										}),
										'component',
										SecuritySubPage,
										282,
										4,
										{ componentTag: 'PanelRow' }
									);

									reset(div_1);
									transition(3, div_1, () => slide);
									append($$anchor, div_1);
								};

								add_svelte_meta(
									() => if_block(node_29, ($$render) => {
										if (!$current_settings()["object-ownership-enforced"] && get(objectOwnershipEnforced) && $delivery_provider().object_ownership_supported) $$render(consequent_9);
									}),
									'if',
									SecuritySubPage,
									280,
									2
								);
							}

							append($$anchor, fragment_11);
						}),
						$$slots: { default: true }
					}),
					'component',
					SecuritySubPage,
					254,
					1,
					{ componentTag: 'Panel' }
				);

				var node_32 = sibling(node_16, 2);

				add_svelte_meta(
					() => BackNextButtonsRow(node_32, {
						onNext: handleNext,
						get nextText() {
							return get(nextText);
						},

						get nextDisabled() {
							return get(nextDisabled);
						}
					}),
					'component',
					SecuritySubPage,
					289,
					1,
					{ componentTag: 'BackNextButtonsRow' }
				);

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		SecuritySubPage,
		218,
		0,
		{ componentTag: 'SubPage' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

DeliveryPage[FILENAME] = 'ui/components/DeliveryPage.svelte';

var root_5$1 = add_locations(from_html(`<div class="row"><!> <p class="speed"></p> <p class="private-media"></p> <!></div>`), DeliveryPage[FILENAME], [[163, 6, [[171, 7], [172, 7]]]]);
var root_8$1 = add_locations(from_html(`<input type="text" class="cdn-name" id="cdn-name" name="cdn-name" minlength="4"/>`), DeliveryPage[FILENAME], [[183, 5]]);
var root_1$8 = add_locations(from_html(`<!> <h2 class="page-title"> </h2> <div class="delivery-provider-settings-page wrapper"><!> <!> <!></div>`, 1), DeliveryPage[FILENAME], [[156, 1], [158, 1]]);

function DeliveryPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, DeliveryPage);

	const $current_settings = () => (
		validate_store(current_settings, 'current_settings'),
		store_get(current_settings, '$current_settings', $$stores)
	);

	const $defined_settings = () => (
		validate_store(defined_settings, 'defined_settings'),
		store_get(defined_settings, '$defined_settings', $$stores)
	);

	const $needs_refresh = () => (
		validate_store(needs_refresh, 'needs_refresh'),
		store_get(needs_refresh, '$needs_refresh', $$stores)
	);

	const $delivery_provider = () => (
		validate_store(delivery_provider, 'delivery_provider'),
		store_get(delivery_provider, '$delivery_provider', $$stores)
	);

	const $settingsLocked = () => (
		validate_store(settingsLocked, 'settingsLocked'),
		store_get(settingsLocked, '$settingsLocked', $$stores)
	);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const $delivery_providers = () => (
		validate_store(delivery_providers, 'delivery_providers'),
		store_get(delivery_providers, '$delivery_providers', $$stores)
	);

	const $storage_provider = () => (
		validate_store(storage_provider, 'storage_provider'),
		store_get(storage_provider, '$storage_provider', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, "delivery-provider");

	// Let all child components know if settings are currently locked.
	setContext("settingsLocked", settingsLocked);

	// As this page does not directly alter the settings store until done,
	// we need to keep track of any changes made elsewhere and prompt
	// the user to refresh the page.
	let saving = tag(state(false), 'saving');

	const previousSettings = { ...$current_settings() };
	const previousDefines = { ...$defined_settings() };

	user_pre_effect(() => {
		store_set(needs_refresh, $needs_refresh() || needsRefresh(get(saving), previousSettings, $current_settings(), previousDefines, $defined_settings()));
	});

	// Start with a copy of the current delivery provider.
	let deliveryProvider = tag(state(proxy({ ...$delivery_provider() })), 'deliveryProvider');

	let defined = tag(user_derived(() => $defined_settings().includes("delivery-provider")), 'defined');
	let disabled = tag(user_derived(() => get(defined) || $settingsLocked()), 'disabled');
	let serviceName = tag(state(proxy($settings()["delivery-provider-service-name"])), 'serviceName');
	let serviceNameDefined = tag(user_derived(() => $defined_settings().includes("delivery-provider-service-name")), 'serviceNameDefined');
	let serviceNameDisabled = tag(user_derived(() => get(serviceNameDefined) || $settingsLocked()), 'serviceNameDisabled');

	/**
	 * Returns an array of delivery providers that can be used with the currently configured storage provider.
	 *
	 * @return {array}
	 */
	function supportedDeliveryProviders() {
		return Object.values($delivery_providers()).filter((provider) => strict_equals(provider.supported_storage_providers.length, 0) || provider.supported_storage_providers.includes($storage_provider().provider_key_name));
	}

	/**
	 * Determines whether the Next button should be disabled or not and returns a suitable reason.
	 *
	 * @param {Object} provider
	 * @param {string} providerName
	 * @param {boolean} settingsLocked
	 * @param {boolean} needsRefresh
	 *
	 * @return {string}
	 */
	function getNextDisabledMessage(provider, providerName, settingsLocked, needsRefresh) {
		let message = "";

		if (settingsLocked || needsRefresh) {
			message = $strings().settings_locked;
		} else if (provider.provider_service_name_override_allowed && providerName.trim().length < 1) {
			message = $strings().no_delivery_provider_name;
		} else if (provider.provider_service_name_override_allowed && providerName.trim().length < 4) {
			message = $strings().delivery_provider_name_short;
		} else if (strict_equals(get(deliveryProvider).provider_key_name, $delivery_provider().provider_key_name) && strict_equals(providerName, $settings()["delivery-provider-service-name"])) {
			message = $strings().nothing_to_save;
		}

		return message;
	}

	let nextDisabledMessage = tag(user_derived(() => getNextDisabledMessage(get(deliveryProvider), get(serviceName), $settingsLocked(), $needs_refresh())), 'nextDisabledMessage');

	/**
	 * Handles choosing a different delivery provider.
	 *
	 * @param {Object} provider
	 */
	function handleChooseProvider(provider) {
		if (get(disabled)) {
			return;
		}

		set(deliveryProvider, provider, true);
	}

	/**
	 * Handles a Next button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleNext() {
		set(saving, true);
		appState.pausePeriodicFetch();
		store_mutate(settings, untrack($settings)["delivery-provider"] = get(deliveryProvider).provider_key_name, untrack($settings));
		store_mutate(settings, untrack($settings)["delivery-provider-service-name"] = get(serviceName), untrack($settings));

		const result = (await track_reactivity_loss(settings.save()))();

		// If something went wrong, don't move onto next step.
		if (result.hasOwnProperty("saved") && !result.saved) {
			settings.reset();
			set(saving, false);
			(await track_reactivity_loss(appState.resumePeriodicFetch()))();
			scrollNotificationsIntoView();

			return;
		}

		store_set(revalidatingSettings, true);

		const statePromise = appState.resumePeriodicFetch();

		$$props.onRouteEvent({
			event: "settings.save",
			data: result,
			default: "/media/delivery"
		});

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		(await track_reactivity_loss(statePromise))();

		store_set(revalidatingSettings, false);
	}

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => Page($$anchor, {
			get name() {
				return name();
			},
			subpage: true,
			get onRouteEvent() {
				return $$props.onRouteEvent;
			},

			children: wrap_snippet(DeliveryPage, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$8();
				var node = first_child(fragment_1);

				add_svelte_meta(
					() => Notifications(node, {
						get tab() {
							return name();
						},
						tabParent: 'media'
					}),
					'component',
					DeliveryPage,
					155,
					1,
					{ componentTag: 'Notifications' }
				);

				var h2 = sibling(node, 2);
				var text = child(h2, true);

				reset(h2);

				var div = sibling(h2, 2);
				var node_1 = child(div);

				add_svelte_meta(
					() => Panel(node_1, {
						get heading() {
							return $strings().select_delivery_provider_title;
						},

						get defined() {
							return get(defined);
						},
						multi: true,
						children: wrap_snippet(DeliveryPage, ($$anchor, $$slotProps) => {
							add_svelte_meta(
								() => PanelRow($$anchor, {
									class: 'body flex-column delivery-provider-buttons',
									children: wrap_snippet(DeliveryPage, ($$anchor, $$slotProps) => {
										var fragment_3 = comment();
										var node_2 = first_child(fragment_3);

										add_svelte_meta(
											() => each(node_2, 17, supportedDeliveryProviders, index, ($$anchor, provider) => {
												var fragment_4 = comment();
												var node_3 = first_child(fragment_4);

												{
													var consequent = ($$anchor) => {
														var div_1 = root_5$1();
														var node_4 = child(div_1);

														{
															let $0 = user_derived(() => strict_equals(get(provider).provider_key_name, get(deliveryProvider).provider_key_name));

															add_svelte_meta(
																() => TabButton(node_4, {
																	get active() {
																		return get($0);
																	},

																	get disabled() {
																		return get(disabled);
																	},

																	get icon() {
																		return get(provider).icon;
																	},

																	get text() {
																		return get(provider).default_provider_service_name;
																	},

																	onclick: (event) => {
																		event.preventDefault();
																		handleChooseProvider(get(provider));
																	}
																}),
																'component',
																DeliveryPage,
																164,
																7,
																{ componentTag: 'TabButton' }
															);
														}

														var p = sibling(node_4, 2);

														html(p, () => get(provider).edge_server_support_desc, true);
														reset(p);

														var p_1 = sibling(p, 2);

														html(p_1, () => get(provider).signed_urls_support_desc, true);
														reset(p_1);

														var node_5 = sibling(p_1, 2);

														add_svelte_meta(
															() => HelpButton(node_5, {
																get url() {
																	return get(provider).provider_service_quick_start_url;
																},

																get desc() {
																	return $strings().view_quick_start_guide;
																}
															}),
															'component',
															DeliveryPage,
															173,
															7,
															{ componentTag: 'HelpButton' }
														);

														reset(div_1);
														append($$anchor, div_1);
													};

													add_svelte_meta(
														() => if_block(node_3, ($$render) => {
															if (!get(provider).is_deprecated || strict_equals(get(provider).provider_key_name, get(deliveryProvider).provider_key_name)) $$render(consequent);
														}),
														'if',
														DeliveryPage,
														162,
														5
													);
												}

												append($$anchor, fragment_4);
											}),
											'each',
											DeliveryPage,
											161,
											4
										);

										append($$anchor, fragment_3);
									}),
									$$slots: { default: true }
								}),
								'component',
								DeliveryPage,
								160,
								3,
								{ componentTag: 'PanelRow' }
							);
						}),
						$$slots: { default: true }
					}),
					'component',
					DeliveryPage,
					159,
					2,
					{ componentTag: 'Panel' }
				);

				var node_6 = sibling(node_1, 2);

				{
					var consequent_1 = ($$anchor) => {
						add_svelte_meta(
							() => Panel($$anchor, {
								get heading() {
									return $strings().enter_other_cdn_name_title;
								},

								get defined() {
									return get(serviceNameDefined);
								},
								multi: true,
								children: wrap_snippet(DeliveryPage, ($$anchor, $$slotProps) => {
									add_svelte_meta(
										() => PanelRow($$anchor, {
											class: 'body flex-column',
											children: wrap_snippet(DeliveryPage, ($$anchor, $$slotProps) => {
												var input = root_8$1();

												remove_input_defaults(input);

												template_effect(() => {
													set_attribute(input, 'placeholder', $strings().enter_other_cdn_name_placeholder);
													input.disabled = get(serviceNameDisabled);
												});

												bind_value(
													input,
													function get$1() {
														return get(serviceName);
													},
													function set$1($$value) {
														set(serviceName, $$value);
													}
												);

												append($$anchor, input);
											}),
											$$slots: { default: true }
										}),
										'component',
										DeliveryPage,
										182,
										4,
										{ componentTag: 'PanelRow' }
									);
								}),
								$$slots: { default: true }
							}),
							'component',
							DeliveryPage,
							181,
							3,
							{ componentTag: 'Panel' }
						);
					};

					add_svelte_meta(
						() => if_block(node_6, ($$render) => {
							if (get(deliveryProvider).provider_service_name_override_allowed) $$render(consequent_1);
						}),
						'if',
						DeliveryPage,
						180,
						2
					);
				}

				var node_7 = sibling(node_6, 2);

				add_svelte_meta(
					() => BackNextButtonsRow(node_7, {
						onNext: handleNext,
						get nextText() {
							return $strings().save_delivery_provider;
						},

						get nextDisabled() {
							return get(nextDisabledMessage);
						},

						get nextTitle() {
							return get(nextDisabledMessage);
						}
					}),
					'component',
					DeliveryPage,
					197,
					2,
					{ componentTag: 'BackNextButtonsRow' }
				);

				reset(div);
				template_effect(() => set_text(text, $strings().delivery_title));
				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		DeliveryPage,
		154,
		0,
		{ componentTag: 'Page' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

// Default pages, having a title means inclusion in main tabs.
// NOTE: get() only resolves after initialization, hence arrow functions for getting titles.
const defaultPages = [
	{
		position: 0,
		name: "media-library",
		title: () => get$1( strings ).media_tab_title,
		nav: true,
		route: "/",
		routeMatcher: /^\/(media\/.*)*$/,
		component: MediaPage,
		default: true
	},
	{
		position: 200,
		name: "storage",
		route: "/storage/*",
		component: StoragePage
	},
	{
		position: 210,
		name: "storage-provider",
		title: () => get$1( strings ).storage_provider_tab_title,
		subNav: true,
		route: "/storage/provider",
		component: StorageProviderSubPage,
		default: true,
		events: {
			"page.initial.settings": ( data ) => {
				// We need Storage Provider credentials for some pages to be useful.
				if ( data.hasOwnProperty( "location" ) && get$1( needs_access_keys ) && !get$1( is_plugin_setup ) ) {
					for ( const prefix of ["/storage", "/media", "/delivery"] ) {
						if ( data.location.startsWith( prefix ) ) {
							return true;
						}
					}

					return data.location === "/";
				}

				return false;
			}
		}
	},
	{
		position: 220,
		name: "bucket",
		title: () => get$1( strings ).bucket_tab_title,
		subNav: true,
		route: "/storage/bucket",
		component: BucketSettingsSubPage,
		enabled: () => {
			return !get$1( needs_access_keys );
		},
		events: {
			"page.initial.settings": ( data ) => {
				// We need a bucket and region to have been verified before some pages are useful.
				if ( data.hasOwnProperty( "location" ) && !get$1( needs_access_keys ) && !get$1( is_plugin_setup ) ) {
					for ( const prefix of ["/storage", "/media", "/delivery"] ) {
						if ( data.location.startsWith( prefix ) ) {
							return true;
						}
					}

					return data.location === "/";
				}

				return false;
			},
			"settings.save": ( data ) => {
				// If currently in /storage/provider route, bucket is always next, assuming storage provider set up correctly.
				return get$1( location$1 ) === "/storage/provider" && !get$1( needs_access_keys );
			}
		}
	},
	{
		position: 230,
		name: "security",
		title: () => get$1( strings ).security_tab_title,
		subNav: true,
		route: "/storage/security",
		component: SecuritySubPage,
		enabled: () => {
			return get$1( is_plugin_setup_with_credentials ) && !get$1( storage_provider ).requires_acls;
		},
		events: {
			"settings.save": ( data ) => {
				// If currently in /storage/bucket route,
				// and storage provider does not require ACLs,
				// and bucket wasn't just created during initial set up
				// with delivery provider compatible access control,
				// then security is next.
				if (
					get$1( location$1 ) === "/storage/bucket" &&
					get$1( is_plugin_setup_with_credentials ) &&
					!get$1( storage_provider ).requires_acls &&
					(
						!data.hasOwnProperty( "bucketSource" ) || // unexpected data issue
						data.bucketSource !== "new" || // bucket not created
						!data.hasOwnProperty( "initialSettings" ) || // unexpected data issue
						!data.initialSettings.hasOwnProperty( "bucket" ) || // unexpected data issue
						data.initialSettings.bucket.length > 0 || // bucket previously set
						!data.hasOwnProperty( "settings" ) || // unexpected data issue
						!data.settings.hasOwnProperty( "use-bucket-acls" ) || // unexpected data issue
						(
							!data.settings[ "use-bucket-acls" ] && // bucket not using ACLs ...
							get$1( delivery_provider ).requires_acls // ... but delivery provider needs ACLs
						)
					)
				) {
					return true;
				}

				return false;
			}
		}
	},
	{
		position: 300,
		name: "delivery",
		route: "/delivery/*",
		component: DeliveryPage
	},
];

Upsell[FILENAME] = 'ui/components/Upsell.svelte';

var root_2$4 = add_locations(from_html(`<li class="svelte-1rikdvh"><img class="svelte-1rikdvh"/> <span> </span></li>`), Upsell[FILENAME], [[26, 4, [[27, 5], [28, 5]]]]);

var root_1$7 = add_locations(from_html(`<div class="branding"></div> <div class="content svelte-1rikdvh"><div class="heading svelte-1rikdvh"><!></div> <div class="description svelte-1rikdvh"><!></div> <div class="benefits svelte-1rikdvh"></div> <div class="call-to-action svelte-1rikdvh"><!> <div class="note svelte-1rikdvh"><!></div></div></div>`, 1), Upsell[FILENAME], [
	[14, 1],
	[15, 1, [[16, 2], [20, 2], [24, 2], [33, 2, [[35, 3]]]]]
]);

function Upsell($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Upsell);

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => Panel($$anchor, {
			name: 'upsell',
			class: 'upsell-panel',
			children: wrap_snippet(Upsell, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$7();
				var div = sibling(first_child(fragment_1), 2);
				var div_1 = child(div);
				var node = child(div_1);

				add_svelte_meta(() => snippet(node, () => $$props.heading ?? noop), 'render', Upsell, 17, 3);
				reset(div_1);

				var div_2 = sibling(div_1, 2);
				var node_1 = child(div_2);

				add_svelte_meta(() => snippet(node_1, () => $$props.description ?? noop), 'render', Upsell, 21, 3);
				reset(div_2);

				var div_3 = sibling(div_2, 2);

				add_svelte_meta(
					() => each(div_3, 21, () => $$props.benefits, index, ($$anchor, benefit) => {
						var li = root_2$4();
						var img = child(li);
						var span = sibling(img, 2);
						var text = child(span, true);

						reset(span);
						reset(li);

						template_effect(() => {
							set_attribute(img, 'src', get(benefit).icon);
							set_attribute(img, 'alt', get(benefit).alt);
							set_text(text, get(benefit).text);
						});

						append($$anchor, li);
					}),
					'each',
					Upsell,
					25,
					3
				);

				reset(div_3);

				var div_4 = sibling(div_3, 2);
				var node_2 = child(div_4);

				add_svelte_meta(() => snippet(node_2, () => $$props.call_to_action ?? noop), 'render', Upsell, 34, 3);

				var div_5 = sibling(node_2, 2);
				var node_3 = child(div_5);

				add_svelte_meta(() => snippet(node_3, () => $$props.call_to_action_note ?? noop), 'render', Upsell, 36, 4);
				reset(div_5);
				reset(div_4);
				reset(div);
				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		Upsell,
		13,
		0,
		{ componentTag: 'Panel' }
	);

	return pop($$exports);
}

AssetsUpgrade[FILENAME] = 'ui/components/AssetsUpgrade.svelte';

var root_1$6 = add_locations(from_html(`<div> </div>`), AssetsUpgrade[FILENAME], [[26, 2]]);
var root_2$3 = add_locations(from_html(`<div></div>`), AssetsUpgrade[FILENAME], [[30, 2]]);
var root_3$2 = add_locations(from_html(`<a class="button btn-lg btn-primary"><img alt="stars icon" style="margin-right: 5px;"/> </a>`), AssetsUpgrade[FILENAME], [[34, 2, [[35, 3]]]]);

function AssetsUpgrade($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, AssetsUpgrade);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	let benefits = [
		{
			icon: $urls().assets + "img/icon/fonts.svg",
			alt: "js icon",
			text: $strings().assets_upsell_benefits.js
		},

		{
			icon: $urls().assets + "img/icon/css.svg",
			alt: "css icon",
			text: $strings().assets_upsell_benefits.css
		},

		{
			icon: $urls().assets + "img/icon/fonts.svg",
			alt: "fonts icon",
			text: $strings().assets_upsell_benefits.fonts
		}
	];

	var $$exports = { ...legacy_api() };

	init();

	{
		const heading = wrap_snippet(AssetsUpgrade, function ($$anchor) {
			validate_snippet_args(...arguments);

			var div = root_1$6();
			var text = child(div, true);

			reset(div);
			template_effect(() => set_text(text, $strings().assets_upsell_heading));
			append($$anchor, div);
		});

		const description = wrap_snippet(AssetsUpgrade, function ($$anchor) {
			validate_snippet_args(...arguments);

			var div_1 = root_2$3();

			html(div_1, () => $strings().assets_upsell_description, true);
			reset(div_1);
			append($$anchor, div_1);
		});

		const call_to_action = wrap_snippet(AssetsUpgrade, function ($$anchor) {
			validate_snippet_args(...arguments);

			var a = root_3$2();
			var img = child(a);
			var text_1 = sibling(img);

			reset(a);

			template_effect(() => {
				set_attribute(a, 'href', $urls().upsell_discount_assets);
				set_attribute(img, 'src', $urls().assets + "img/icon/stars.svg");
				set_text(text_1, ` ${$strings().assets_upsell_cta ?? ''}`);
			});

			append($$anchor, a);
		});

		add_svelte_meta(
			() => Upsell($$anchor, {
				get benefits() {
					return benefits;
				},
				heading,
				description,
				call_to_action,
				$$slots: { heading: true, description: true, call_to_action: true }
			}),
			'component',
			AssetsUpgrade,
			24,
			0,
			{ componentTag: 'Upsell' }
		);
	}

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

AssetsPage[FILENAME] = 'ui/components/AssetsPage.svelte';

var root_1$5 = add_locations(from_html(`<h2 class="page-title"> </h2> <!>`, 1), AssetsPage[FILENAME], [[17, 1]]);

function AssetsPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, AssetsPage);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, "assets");

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => Page($$anchor, {
			get name() {
				return name();
			},

			get onRouteEvent() {
				return $$props.onRouteEvent;
			},

			children: wrap_snippet(AssetsPage, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$5();
				var h2 = first_child(fragment_1);
				var text = child(h2, true);

				reset(h2);

				var node = sibling(h2, 2);

				add_svelte_meta(() => AssetsUpgrade(node, {}), 'component', AssetsPage, 18, 1, { componentTag: 'AssetsUpgrade' });
				template_effect(() => set_text(text, $strings().assets_title));
				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		AssetsPage,
		16,
		0,
		{ componentTag: 'Page' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

ToolsUpgrade[FILENAME] = 'ui/components/ToolsUpgrade.svelte';

var root_1$4 = add_locations(from_html(`<div> </div>`), ToolsUpgrade[FILENAME], [[31, 2]]);
var root_2$2 = add_locations(from_html(`<div></div>`), ToolsUpgrade[FILENAME], [[35, 2]]);
var root_3$1 = add_locations(from_html(`<a class="button btn-lg btn-primary"><img alt="stars icon" style="margin-right: 5px;"/> </a>`), ToolsUpgrade[FILENAME], [[39, 2, [[40, 3]]]]);

function ToolsUpgrade($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, ToolsUpgrade);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	let benefits = [
		{
			icon: $urls().assets + "img/icon/offload-remaining.svg",
			alt: "offload icon",
			text: $strings().tools_upsell_benefits.offload
		},

		{
			icon: $urls().assets + "img/icon/download.svg",
			alt: "download icon",
			text: $strings().tools_upsell_benefits.download
		},

		{
			icon: $urls().assets + "img/icon/remove-from-bucket.svg",
			alt: "remove from bucket icon",
			text: $strings().tools_upsell_benefits.remove_bucket
		},

		{
			icon: $urls().assets + "img/icon/remove-from-server.svg",
			alt: "remove from server icon",
			text: $strings().tools_upsell_benefits.remove_server
		}
	];

	var $$exports = { ...legacy_api() };

	init();

	{
		const heading = wrap_snippet(ToolsUpgrade, function ($$anchor) {
			validate_snippet_args(...arguments);

			var div = root_1$4();
			var text = child(div, true);

			reset(div);
			template_effect(() => set_text(text, $strings().tools_upsell_heading));
			append($$anchor, div);
		});

		const description = wrap_snippet(ToolsUpgrade, function ($$anchor) {
			validate_snippet_args(...arguments);

			var div_1 = root_2$2();

			html(div_1, () => $strings().tools_upsell_description, true);
			reset(div_1);
			append($$anchor, div_1);
		});

		const call_to_action = wrap_snippet(ToolsUpgrade, function ($$anchor) {
			validate_snippet_args(...arguments);

			var a = root_3$1();
			var img = child(a);
			var text_1 = sibling(img);

			reset(a);

			template_effect(() => {
				set_attribute(a, 'href', $urls().upsell_discount_tools);
				set_attribute(img, 'src', $urls().assets + "img/icon/stars.svg");
				set_text(text_1, ` ${$strings().tools_upsell_cta ?? ''}`);
			});

			append($$anchor, a);
		});

		add_svelte_meta(
			() => Upsell($$anchor, {
				get benefits() {
					return benefits;
				},
				heading,
				description,
				call_to_action,
				$$slots: { heading: true, description: true, call_to_action: true }
			}),
			'component',
			ToolsUpgrade,
			29,
			0,
			{ componentTag: 'Upsell' }
		);
	}

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

ToolsPage[FILENAME] = 'ui/components/ToolsPage.svelte';

var root_1$3 = add_locations(from_html(`<!> <h2 class="page-title"> </h2> <!>`, 1), ToolsPage[FILENAME], [[19, 1]]);

function ToolsPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, ToolsPage);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, "tools");

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => Page($$anchor, {
			get name() {
				return name();
			},

			get onRouteEvent() {
				return $$props.onRouteEvent;
			},

			children: wrap_snippet(ToolsPage, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$3();
				var node = first_child(fragment_1);

				add_svelte_meta(
					() => Notifications(node, {
						get tab() {
							return name();
						}
					}),
					'component',
					ToolsPage,
					18,
					1,
					{ componentTag: 'Notifications' }
				);

				var h2 = sibling(node, 2);
				var text = child(h2, true);

				reset(h2);

				var node_1 = sibling(h2, 2);

				add_svelte_meta(() => ToolsUpgrade(node_1, {}), 'component', ToolsPage, 20, 1, { componentTag: 'ToolsUpgrade' });
				template_effect(() => set_text(text, $strings().tools_title));
				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		ToolsPage,
		17,
		0,
		{ componentTag: 'Page' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

SupportPage[FILENAME] = 'ui/components/SupportPage.svelte';

var root_2$1 = add_locations(from_html(`<h2 class="page-title"> </h2>`), SupportPage[FILENAME], [[39, 2]]);
var root_4$1 = add_locations(from_html(`<div class="lite-support"><p></p> <p></p> <p></p> <p></p></div>`), SupportPage[FILENAME], [[50, 5, [[51, 6], [52, 6], [53, 6], [54, 6]]]]);

var root_1$2 = add_locations(from_html(`<!> <!> <div class="support-page wrapper"><!> <div class="columns"><div class="support-form"><!> <div class="diagnostic-info"><hr/> <h2 class="page-title"> </h2> <pre> </pre> <a class="button btn-md btn-outline"> </a></div></div> <!></div></div>`, 1), SupportPage[FILENAME], [
	[
		41,
		1,
		[
			[
				45,
				2,
				[[46, 3, [[58, 4, [[59, 5], [60, 5], [61, 5], [62, 5]]]]]]
			]
		]
	]
]);

function SupportPage($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, SupportPage);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $config = () => (
		validate_store(config, 'config'),
		store_get(config, '$config', $$stores)
	);

	const $diagnostics = () => (
		validate_store(diagnostics, 'diagnostics'),
		store_get(diagnostics, '$diagnostics', $$stores)
	);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {any} [title]
	 * @property {import("svelte").Snippet} [header]
	 * @property {import("svelte").Snippet} [content]
	 * @property {import("svelte").Snippet} [footer]
	 * @property {function} [onRouteEvent]
	 */
	/** @type {Props} */
	let name = prop($$props, 'name', 3, "support"),
		title = prop($$props, 'title', 19, () => $strings().support_tab_title);

	onMount(async () => {
		const json = (await track_reactivity_loss(api.get("diagnostics", {})))();

		if (json.hasOwnProperty("diagnostics")) {
			store_mutate(config, untrack($config).diagnostics = json.diagnostics, untrack($config));
		}
	});

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => Page($$anchor, {
			get name() {
				return name();
			},

			get onRouteEvent() {
				return $$props.onRouteEvent;
			},

			children: wrap_snippet(SupportPage, ($$anchor, $$slotProps) => {
				var fragment_1 = root_1$2();
				var node = first_child(fragment_1);

				add_svelte_meta(
					() => Notifications(node, {
						get tab() {
							return name();
						}
					}),
					'component',
					SupportPage,
					37,
					1,
					{ componentTag: 'Notifications' }
				);

				var node_1 = sibling(node, 2);

				{
					var consequent = ($$anchor) => {
						var h2 = root_2$1();
						var text = child(h2, true);

						reset(h2);
						template_effect(() => set_text(text, title()));
						append($$anchor, h2);
					};

					add_svelte_meta(
						() => if_block(node_1, ($$render) => {
							if (title()) $$render(consequent);
						}),
						'if',
						SupportPage,
						38,
						1
					);
				}

				var div = sibling(node_1, 2);
				var node_2 = child(div);

				add_svelte_meta(() => snippet(node_2, () => $$props.header ?? noop), 'render', SupportPage, 43, 2);

				var div_1 = sibling(node_2, 2);
				var div_2 = child(div_1);
				var node_3 = child(div_2);

				{
					var consequent_1 = ($$anchor) => {
						var fragment_2 = comment();
						var node_4 = first_child(fragment_2);

						add_svelte_meta(() => snippet(node_4, () => $$props.content), 'render', SupportPage, 48, 5);
						append($$anchor, fragment_2);
					};

					var alternate = ($$anchor) => {
						var div_3 = root_4$1();
						var p = child(div_3);

						html(p, () => $strings().no_support, true);
						reset(p);

						var p_1 = sibling(p, 2);

						html(p_1, () => $strings().community_support, true);
						reset(p_1);

						var p_2 = sibling(p_1, 2);

						html(p_2, () => $strings().upgrade_for_support, true);
						reset(p_2);

						var p_3 = sibling(p_2, 2);

						html(p_3, () => $strings().report_a_bug, true);
						reset(p_3);
						reset(div_3);
						append($$anchor, div_3);
					};

					add_svelte_meta(
						() => if_block(node_3, ($$render) => {
							if ($$props.content) $$render(consequent_1); else $$render(alternate, -1);
						}),
						'if',
						SupportPage,
						47,
						4
					);
				}

				var div_4 = sibling(node_3, 2);
				var h2_1 = sibling(child(div_4), 2);
				var text_1 = child(h2_1, true);

				reset(h2_1);

				var pre = sibling(h2_1, 2);
				var text_2 = child(pre, true);

				reset(pre);

				var a = sibling(pre, 2);
				var text_3 = child(a, true);

				reset(a);
				reset(div_4);
				reset(div_2);

				var node_5 = sibling(div_2, 2);

				add_svelte_meta(() => snippet(node_5, () => $$props.footer ?? noop), 'render', SupportPage, 66, 3);
				reset(div_1);
				reset(div);

				template_effect(() => {
					set_text(text_1, $strings().diagnostic_info_title);
					set_text(text_2, $diagnostics());
					set_attribute(a, 'href', $urls().download_diagnostics);
					set_text(text_3, $strings().download_diagnostics);
				});

				append($$anchor, fragment_1);
			}),
			$$slots: { default: true }
		}),
		'component',
		SupportPage,
		36,
		0,
		{ componentTag: 'Page' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

/**
 * Adds Lite specific pages.
 */
function addPages() {
	pages.add(
		{
			position: 10,
			name: "assets",
			title: () => get$1( strings ).assets_tab_title,
			nav: true,
			route: "/assets",
			component: AssetsPage
		}
	);
	pages.add(
		{
			position: 20,
			name: "tools",
			title: () => get$1( strings ).tools_tab_title,
			nav: true,
			route: "/tools",
			component: ToolsPage
		}
	);
	pages.add(
		{
			position: 100,
			name: "support",
			title: () => get$1( strings ).support_tab_title,
			nav: true,
			route: "/support",
			component: SupportPage
		}
	);
}

const settingsNotifications = {
	/**
	 * Process local and server settings to return a new Map of inline notifications.
	 *
	 * @param {Map} notifications
	 * @param {Object} settings
	 * @param {Object} current_settings
	 * @param {Object} strings
	 *
	 * @return {Map<string, Map<string, Object>>} keyed by setting name, containing map of notification objects keyed by id.
	 */
	process: ( notifications, settings, current_settings, strings ) => {
		// remove-local-file
		if ( settings.hasOwnProperty( "remove-local-file" ) && settings[ "remove-local-file" ] ) {
			let entries = notifications.has( "remove-local-file" ) ? notifications.get( "remove-local-file" ) : new Map();

			if ( settings.hasOwnProperty( "serve-from-s3" ) && !settings[ "serve-from-s3" ] ) {
				if ( !entries.has( "lost-files-notice" ) ) {
					entries.set( "lost-files-notice", {
						inline: true,
						type: "error",
						heading: strings.lost_files_notice_heading,
						message: strings.lost_files_notice_message
					} );
				}
			} else {
				entries.delete( "lost-files-notice" );
			}

			// Show inline warning about potential compatibility issues
			// when turning on setting for the first time.
			if (
				!entries.has( "remove-local-file-notice" ) &&
				current_settings.hasOwnProperty( "remove-local-file" ) &&
				!current_settings[ "remove-local-file" ]
			) {
				entries.set( "remove-local-file-notice", {
					inline: true,
					type: "warning",
					message: strings.remove_local_file_message
				} );
			}

			notifications.set( "remove-local-file", entries );
		} else {
			notifications.delete( "remove-local-file" );
		}

		return notifications;
	}
};

Header[FILENAME] = 'ui/components/Header.svelte';

var root$8 = add_locations(from_html(`<div class="header"><div class="header-wrapper"><img class="medallion"/> <h1> </h1> <div class="licence"><!></div></div></div>`), Header[FILENAME], [[14, 0, [[15, 1, [[16, 2], [17, 2], [18, 2]]]]]]);

function Header($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Header);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $config = () => (
		validate_store(config, 'config'),
		store_get(config, '$config', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {import("svelte").Snippet} [children]
	 */
	/** @type {Props} */
	let header_img_url = tag(user_derived(() => $urls().assets + "img/brand/ome-medallion.svg"), 'header_img_url');

	var $$exports = { ...legacy_api() };
	var div = root$8();
	var div_1 = child(div);
	var img = child(div_1);
	var h1 = sibling(img, 2);
	var text = child(h1);

	var div_2 = sibling(h1, 2);
	var node = child(div_2);

	add_svelte_meta(() => snippet(node, () => $$props.children ?? noop), 'render', Header, 19, 3);

	template_effect(() => {
		set_attribute(img, 'src', get(header_img_url));
		set_attribute(img, 'alt', `${$config().title ?? ''} logo`);
		set_text(text, $config().title);
	});

	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

Settings[FILENAME] = 'ui/components/Settings.svelte';

var root$7 = add_locations(from_html(`<!> <!> <!>`, 1), Settings[FILENAME], []);

function Settings($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Settings);

	const $config = () => (
		validate_store(config, 'config'),
		store_get(config, '$config', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {any} [header] - These components can be overridden.
	 * @property {any} [footer]
	 * @property {import("svelte").Snippet} [children]
	 */
	/** @type {Props} */
	let header = prop($$props, 'header', 3, Header),
		footer = prop($$props, 'footer', 3, null);

	// We need a disassociated copy of the initial settings to work with.
	settings.set({ ...$config().settings });

	// We might have some initial notifications to display too.
	if ($config().notifications.length) {
		for (const notification of $config().notifications) {
			notifications.add(notification);
		}
	}

	onMount(() => {
		// Periodically check the state.
		appState.startPeriodicFetch();

		// Be a good citizen and clean up the timer when exiting our settings.
		return () => appState.stopPeriodicFetch();
	});

	var $$exports = { ...legacy_api() };
	var fragment = root$7();
	var node = first_child(fragment);

	{
		var consequent = ($$anchor) => {
			const HeaderComponent = tag(user_derived(header), 'HeaderComponent');

			get(HeaderComponent);

			var fragment_1 = comment();
			var node_1 = first_child(fragment_1);

			add_svelte_meta(
				() => component(node_1, () => get(HeaderComponent), ($$anchor, HeaderComponent_1) => {
					HeaderComponent_1($$anchor, {});
				}),
				'component',
				Settings,
				37,
				1,
				{ componentTag: 'HeaderComponent' }
			);

			append($$anchor, fragment_1);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (header()) $$render(consequent);
			}),
			'if',
			Settings,
			35,
			0
		);
	}

	var node_2 = sibling(node, 2);

	{
		var consequent_1 = ($$anchor) => {
			var fragment_2 = comment();
			var node_3 = first_child(fragment_2);

			add_svelte_meta(() => snippet(node_3, () => $$props.children), 'render', Settings, 40, 1);
			append($$anchor, fragment_2);
		};

		var alternate = ($$anchor) => {};

		add_svelte_meta(
			() => if_block(node_2, ($$render) => {
				if ($$props.children) $$render(consequent_1); else $$render(alternate, -1);
			}),
			'if',
			Settings,
			39,
			0
		);
	}

	var node_4 = sibling(node_2, 2);

	{
		var consequent_2 = ($$anchor) => {
			const FooterComponent = tag(user_derived(footer), 'FooterComponent');

			get(FooterComponent);

			var fragment_3 = comment();
			var node_5 = first_child(fragment_3);

			add_svelte_meta(
				() => component(node_5, () => get(FooterComponent), ($$anchor, FooterComponent_1) => {
					FooterComponent_1($$anchor, {});
				}),
				'component',
				Settings,
				46,
				1,
				{ componentTag: 'FooterComponent' }
			);

			append($$anchor, fragment_3);
		};

		add_svelte_meta(
			() => if_block(node_4, ($$render) => {
				if (footer()) $$render(consequent_2);
			}),
			'if',
			Settings,
			44,
			0
		);
	}

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

Header_1[FILENAME] = 'ui/lite/Header.svelte';

var root_1$1 = add_locations(from_html(`<a class="button btn-lg btn-primary"> </a>`), Header_1[FILENAME], [[7, 1]]);

function Header_1($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, Header_1);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();
	var $$exports = { ...legacy_api() };

	init();

	add_svelte_meta(
		() => Header($$anchor, {
			children: wrap_snippet(Header_1, ($$anchor, $$slotProps) => {
				var a = root_1$1();
				var text = child(a, true);

				reset(a);

				template_effect(() => {
					set_attribute(a, 'href', $urls().header_discount);
					set_text(text, $strings().get_licence_discount_text);
				});

				append($$anchor, a);
			}),
			$$slots: { default: true }
		}),
		'component',
		Header_1,
		6,
		0,
		{ componentTag: 'Header' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

NavItem[FILENAME] = 'ui/components/NavItem.svelte';

var root$6 = add_locations(from_html(`<li><a> </a></li>`), NavItem[FILENAME], [[11, 0, [[12, 1]]]]);

function NavItem($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, NavItem);

	let focus = tag(state(false), 'focus');
	let hover = tag(state(false), 'hover');
	var $$exports = { ...legacy_api() };
	var li = root$6();
	let classes;
	var a = child(li);
	var text = child(a);
	action(a, ($$node) => link?.($$node));
	action(li, ($$node, $$action_arg) => active?.($$node, $$action_arg), () => $$props.tab.routeMatcher ? $$props.tab.routeMatcher : $$props.tab.route);

	template_effect(
		($0, $1) => {
			classes = set_class(li, 1, 'nav-item', null, classes, { focus: get(focus), hover: get(hover) });
			set_attribute(a, 'href', $$props.tab.route);
			set_attribute(a, 'title', $0);
			set_text(text, $1);
		},
		[() => $$props.tab.title(), () => $$props.tab.title()]
	);

	delegated('focusin', a, function focusin() {
		return set(focus, true);
	});

	delegated('focusout', a, function focusout() {
		return set(focus, false);
	});

	event('mouseenter', a, function mouseenter() {
		return set(hover, true);
	});

	event('mouseleave', a, function mouseleave() {
		return set(hover, false);
	});

	append($$anchor, li);

	return pop($$exports);
}

delegate(['focusin', 'focusout']);

/**
 * Get the user's current locale string.
 *
 * @return {string}
 */
function getLocale() {
	return (navigator.languages && navigator.languages.length) ? navigator.languages[ 0 ] : navigator.language;
}

/**
 * Get number formatted for user's current locale.
 *
 * @param {number} num
 *
 * @return {string}
 */
function numToString( num ) {
	return Intl.NumberFormat( getLocale() ).format( num );
}

/*
Adapted from https://github.com/mattdesl
Distributed under MIT License https://github.com/mattdesl/eases/blob/master/LICENSE.md
*/

/**
 * @param {number} t
 * @returns {number}
 */
function linear(t) {
	return t;
}

/**
 * @param {number} t
 * @returns {number}
 */
function cubicOut(t) {
	const f = t - 1.0;
	return f * f * f + 1.0;
}

/**
 * @param {any} obj
 * @returns {obj is Date}
 */
function is_date(obj) {
	return Object.prototype.toString.call(obj) === '[object Date]';
}

/** @import { Task } from '../internal/client/types' */
/** @import { Tweened, TweenOptions } from './public' */

/**
 * @template T
 * @param {T} a
 * @param {T} b
 * @returns {(t: number) => T}
 */
function get_interpolator(a, b) {
	if (a === b || a !== a) return () => a;

	const type = typeof a;
	if (type !== typeof b || Array.isArray(a) !== Array.isArray(b)) {
		throw new Error('Cannot interpolate values of different type');
	}

	if (Array.isArray(a)) {
		const arr = /** @type {Array<any>} */ (b).map((bi, i) => {
			return get_interpolator(/** @type {Array<any>} */ (a)[i], bi);
		});

		// @ts-ignore
		return (t) => arr.map((fn) => fn(t));
	}

	if (type === 'object') {
		if (!a || !b) {
			throw new Error('Object cannot be null');
		}

		if (is_date(a) && is_date(b)) {
			const an = a.getTime();
			const bn = b.getTime();
			const delta = bn - an;

			// @ts-ignore
			return (t) => new Date(an + t * delta);
		}

		const keys = Object.keys(b);

		/** @type {Record<string, (t: number) => T>} */
		const interpolators = {};
		keys.forEach((key) => {
			// @ts-ignore
			interpolators[key] = get_interpolator(a[key], b[key]);
		});

		// @ts-ignore
		return (t) => {
			/** @type {Record<string, any>} */
			const result = {};
			keys.forEach((key) => {
				result[key] = interpolators[key](t);
			});
			return result;
		};
	}

	if (type === 'number') {
		const delta = /** @type {number} */ (b) - /** @type {number} */ (a);
		// @ts-ignore
		return (t) => a + t * delta;
	}

	// for non-numeric values, snap to the final value immediately
	return () => b;
}

/**
 * A wrapper for a value that tweens smoothly to its target value. Changes to `tween.target` will cause `tween.current` to
 * move towards it over time, taking account of the `delay`, `duration` and `easing` options.
 *
 * ```svelte
 * <script>
 * 	import { Tween } from 'svelte/motion';
 *
 * 	const tween = new Tween(0);
 * </script>
 *
 * <input type="range" bind:value={tween.target} />
 * <input type="range" bind:value={tween.current} disabled />
 * ```
 * @template T
 * @since 5.8.0
 */
class Tween {
	#current;
	#target;

	/** @type {TweenOptions<T>} */
	#defaults;

	/** @type {import('../internal/client/types').Task | null} */
	#task = null;

	/**
	 * @param {T} value
	 * @param {TweenOptions<T>} options
	 */
	constructor(value, options = {}) {
		this.#current = state(value);
		this.#target = state(value);
		this.#defaults = options;

		if (DEV) {
			tag(this.#current, 'Tween.current');
			tag(this.#target, 'Tween.target');
		}
	}

	/**
	 * Create a tween whose value is bound to the return value of `fn`. This must be called
	 * inside an effect root (for example, during component initialisation).
	 *
	 * ```svelte
	 * <script>
	 * 	import { Tween } from 'svelte/motion';
	 *
	 * 	let { number } = $props();
	 *
	 * 	const tween = Tween.of(() => number);
	 * </script>
	 * ```
	 * @template U
	 * @param {() => U} fn
	 * @param {TweenOptions<U>} [options]
	 */
	static of(fn, options) {
		const tween = new Tween(fn(), options);

		render_effect(() => {
			tween.set(fn());
		});

		return tween;
	}

	/**
	 * Sets `tween.target` to `value` and returns a `Promise` that resolves if and when `tween.current` catches up to it.
	 *
	 * If `options` are provided, they will override the tween's defaults.
	 * @param {T} value
	 * @param {TweenOptions<T>} [options]
	 * @returns
	 */
	set(value, options) {
		set(this.#target, value);

		let {
			delay = 0,
			duration = 400,
			easing = linear,
			interpolate = get_interpolator
		} = { ...this.#defaults, ...options };

		if (duration === 0) {
			this.#task?.abort();
			set(this.#current, value);
			return Promise.resolve();
		}

		const start = raf.now() + delay;

		/** @type {(t: number) => T} */
		let fn;
		let started = false;
		let previous_task = this.#task;

		this.#task = loop((now) => {
			if (now < start) {
				return true;
			}

			if (!started) {
				started = true;

				const prev = this.#current.v;

				fn = interpolate(prev, value);

				if (typeof duration === 'function') {
					duration = duration(prev, value);
				}

				previous_task?.abort();
			}

			const elapsed = now - start;

			if (elapsed > /** @type {number} */ (duration)) {
				set(this.#current, value);
				return false;
			}

			set(this.#current, fn(easing(elapsed / /** @type {number} */ (duration))));
			return true;
		});

		return this.#task.promise;
	}

	get current() {
		return get(this.#current);
	}

	get target() {
		return get(this.#target);
	}

	set target(v) {
		this.set(v);
	}
}

ProgressBar[FILENAME] = 'ui/components/ProgressBar.svelte';

var root$5 = add_locations(from_html(`<div><span></span></div>`), ProgressBar[FILENAME], [[62, 0, [[71, 1]]]]);

function ProgressBar($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, ProgressBar);

	/**
	 * @typedef {Object} Props
	 * @property {number} [percentComplete]
	 * @property {boolean} [starting]
	 * @property {boolean} [running]
	 * @property {boolean} [paused]
	 * @property {string} [title]
	 * @property {function} [onclick]
	 * @property {function} [onmouseenter]
	 * @property {function} [onmouseleave]
	 */
	/** @type {Props} */
	let percentComplete = prop($$props, 'percentComplete', 3, 0),
		starting = prop($$props, 'starting', 3, false),
		running = prop($$props, 'running', 3, false),
		paused = prop($$props, 'paused', 3, false),
		title = prop($$props, 'title', 3, "");

	const progressTween = new Tween(0, { duration: 400, easing: cubicOut });

	/**
	 * Utility function for reactively getting the progress.
	 *
	 * @param {number} percent
	 *
	 * @return {number|*}
	 */
	function getProgress(percent) {
		if (percent < 1) {
			return 0;
		}

		if (percent >= 100) {
			return 100;
		}

		return percent;
	}

	user_effect(() => {
		progressTween.set(getProgress(percentComplete()));
	});

	let complete = tag(user_derived(() => percentComplete() >= 100), 'complete');
	var $$exports = { ...legacy_api() };
	var div = root$5();
	let classes;
	var span = child(div);
	let classes_1;

	template_effect(() => {
		classes = set_class(div, 1, 'progress-bar', null, classes, { stripe: running() && !paused(), animate: starting() });
		set_attribute(div, 'title', title());
		classes_1 = set_class(span, 1, 'indicator animate', null, classes_1, { complete: get(complete), running: running() });
		set_style(span, `width: ${progressTween.current ?? ''}%`);
	});

	delegated('click', div, function (...$$args) {
		apply(() => $$props.onclick, this, $$args, ProgressBar, [67, 2]);
	});

	event('mouseenter', div, function (...$$args) {
		apply(() => $$props.onmouseenter, this, $$args, ProgressBar, [68, 2]);
	});

	event('mouseleave', div, function (...$$args) {
		apply(() => $$props.onmouseleave, this, $$args, ProgressBar, [69, 2]);
	});

	append($$anchor, div);

	return pop($$exports);
}

delegate(['click']);

OffloadStatusFlyout[FILENAME] = 'ui/components/OffloadStatusFlyout.svelte';

var root_5 = add_locations(from_html(`<td class="numeric"><a> </a></td>`), OffloadStatusFlyout[FILENAME], [[172, 7, [[173, 8]]]]);
var root_6 = add_locations(from_html(`<td class="numeric"> </td>`), OffloadStatusFlyout[FILENAME], [[176, 7]]);
var root_7 = add_locations(from_html(`<td class="numeric"><a> </a></td>`), OffloadStatusFlyout[FILENAME], [[179, 7, [[180, 8]]]]);
var root_8 = add_locations(from_html(`<td class="numeric"> </td>`), OffloadStatusFlyout[FILENAME], [[183, 7]]);
var root_4 = add_locations(from_html(`<tr><td> </td><!><!></tr>`), OffloadStatusFlyout[FILENAME], [[169, 5, [[170, 6]]]]);
var root_9 = add_locations(from_html(`<tfoot><tr><td> </td><td class="numeric"> </td><td class="numeric"> </td></tr></tfoot>`), OffloadStatusFlyout[FILENAME], [[190, 5, [[191, 5, [[192, 6], [193, 6], [194, 6]]]]]]);

var root_3 = add_locations(from_html(`<table><thead><tr><th> </th><th class="numeric"> </th><th class="numeric"> </th></tr></thead><tbody></tbody><!></table>`), OffloadStatusFlyout[FILENAME], [
	[
		158,
		3,
		[
			[159, 4, [[160, 4, [[161, 5], [162, 5], [163, 5]]]]],
			[167, 4]
		]
	]
]);

var root_13 = add_locations(from_html(`<p></p>`), OffloadStatusFlyout[FILENAME], [[206, 5]]);
var root_12 = add_locations(from_html(`<!> <a class="button btn-sm btn-primary licence" target="_blank"><img alt="stars icon" style="margin-right: 5px;"/> </a>`, 1), OffloadStatusFlyout[FILENAME], [[208, 4, [[209, 5]]]]);
var root_2 = add_locations(from_html(`<!> <!>`, 1), OffloadStatusFlyout[FILENAME], []);
var root$4 = add_locations(from_html(`<!> <!>`, 1), OffloadStatusFlyout[FILENAME], []);

function OffloadStatusFlyout($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, OffloadStatusFlyout);

	var $$ownership_validator = create_ownership_validator($$props);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $summaryCounts = () => (
		validate_store(summaryCounts, 'summaryCounts'),
		store_get(summaryCounts, '$summaryCounts', $$stores)
	);

	const $counts = () => (
		validate_store(counts, 'counts'),
		store_get(counts, '$counts', $$stores)
	);

	const $offloadRemainingUpsell = () => (
		validate_store(offloadRemainingUpsell, 'offloadRemainingUpsell'),
		store_get(offloadRemainingUpsell, '$offloadRemainingUpsell', $$stores)
	);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {boolean} [expanded]
	 * @property {any} [buttonRef]
	 * @property {any} [panelRef]
	 * @property {boolean} [hasFocus]
	 * @property {boolean} [refreshing]
	 * @property {import("svelte").Snippet} [footer]
	 */
	/** @type {Props} */
	let expanded = prop($$props, 'expanded', 15, false),
		buttonRef = prop($$props, 'buttonRef', 31, () => tag_proxy(proxy({}), 'buttonRef')),
		panelRef = prop($$props, 'panelRef', 31, () => tag_proxy(proxy({}), 'panelRef')),
		hasFocus = prop($$props, 'hasFocus', 15, false),
		refreshing = prop($$props, 'refreshing', 15, false);

	/**
	 * Keep track of when a child control gets mouse focus.
	 */
	function handleMouseEnter() {
		hasFocus(true);
	}

	/**
	 * Keep track of when a child control loses mouse focus.
	 */
	function handleMouseLeave() {
		hasFocus(false);
	}

	/**
	 * When the panel is clicked, select the first focusable element
	 * so that clicking outside the panel triggers a lost focus event.
	 *
	 * @property {Event} [event]
	 */
	function handlePanelClick(event) {
		if (event.target.closest("a")) {
			return;
		}

		event.preventDefault();
		hasFocus(true);

		const firstFocusable = panelRef().querySelector("a:not([tabindex='-1']),button:not([tabindex='-1'])");

		if (firstFocusable) {
			firstFocusable.focus();
		}
	}

	/**
	 * When either the button or panel completely lose focus, close the flyout.
	 *
	 * @param {FocusEvent} event
	 *
	 * @return {boolean}
	 */
	function handleFocusOut(event) {
		if (!expanded()) {
			return false;
		}

		// Mouse click and OffloadStatus control/children no longer have mouse focus.
		if (strict_equals(event.relatedTarget, null) && !hasFocus()) {
			expanded(false);
		}

		// Keyboard focus change and new focused control isn't within OffloadStatus/Flyout.
		if (strict_equals(event.relatedTarget, null, false) && strict_equals(event.relatedTarget, buttonRef(), false) && !panelRef().contains(event.relatedTarget)) {
			expanded(false);
		}
	}

	/**
	 * Handle cancel event from panel and button.
	 */
	function handleCancel() {
		buttonRef().focus();
		expanded(false);
	}

	/**
	 * Manually refresh the media counts.
	 *
	 * @return {Promise<void>}
	 */
	async function handleRefresh() {
		let start = Date.now();

		refreshing(true);

		let params = { refreshMediaCounts: true };
		let json = (await track_reactivity_loss(api.get("state", params)))();

		(await track_reactivity_loss(delayMin(start, 1000)))();
		appState.updateState(json);
		refreshing(false);
		buttonRef().focus();
	}

	var $$exports = { ...legacy_api() };
	var fragment = root$4();
	var node = first_child(fragment);

	{
		let $0 = user_derived(() => expanded() ? $strings().hide_details : $strings().show_details);

		$$ownership_validator.binding('buttonRef', Button, buttonRef);

		add_svelte_meta(
			() => Button(node, {
				expandable: true,
				get expanded() {
					return expanded();
				},
				onclick: () => expanded(!expanded()),
				get title() {
					return get($0);
				},
				onfocusout: handleFocusOut,
				onCancel: handleCancel,
				get ref() {
					return buttonRef();
				},

				set ref($$value) {
					buttonRef($$value);
				}
			}),
			'component',
			OffloadStatusFlyout,
			130,
			0,
			{ componentTag: 'Button' }
		);
	}

	var node_1 = sibling(node, 2);

	{
		var consequent_5 = ($$anchor) => {
			{
				$$ownership_validator.binding('panelRef', Panel, panelRef);

				add_svelte_meta(
					() => Panel($$anchor, {
						multi: true,
						flyout: true,
						refresh: true,
						get refreshing() {
							return refreshing();
						},

						get heading() {
							return $strings().offload_status_title;
						},

						get refreshDesc() {
							return $strings().refresh_media_counts_desc;
						},
						onfocusout: handleFocusOut,
						onmouseenter: handleMouseEnter,
						onmouseleave: handleMouseLeave,
						onmousedown: handleMouseEnter,
						onclick: handlePanelClick,
						onCancel: handleCancel,
						onRefresh: handleRefresh,
						get ref() {
							return panelRef();
						},

						set ref($$value) {
							panelRef($$value);
						},

						children: wrap_snippet(OffloadStatusFlyout, ($$anchor, $$slotProps) => {
							var fragment_2 = root_2();
							var node_2 = first_child(fragment_2);

							add_svelte_meta(
								() => PanelRow(node_2, {
									class: 'summary',
									children: wrap_snippet(OffloadStatusFlyout, ($$anchor, $$slotProps) => {
										var table = root_3();
										var thead = child(table);
										var tr = child(thead);
										var th = child(tr);
										var text = child(th, true);

										reset(th);

										var th_1 = sibling(th);
										var text_1 = child(th_1, true);

										reset(th_1);

										var th_2 = sibling(th_1);
										var text_2 = child(th_2, true);

										reset(th_2);
										reset(tr);
										reset(thead);

										var tbody = sibling(thead);

										add_svelte_meta(
											() => each(tbody, 5, $summaryCounts, (summary) => summary.type, ($$anchor, summary) => {
												var tr_1 = root_4();
												var td = child(tr_1);
												var text_3 = child(td, true);

												reset(td);

												var node_3 = sibling(td);

												{
													var consequent = ($$anchor) => {
														var td_1 = root_5();
														var a = child(td_1);
														var text_4 = child(a, true);

														reset(a);
														reset(td_1);

														template_effect(
															($0) => {
																set_attribute(a, 'href', get(summary).offloaded_url);
																set_text(text_4, $0);
															},
															[() => numToString(get(summary).offloaded)]
														);

														append($$anchor, td_1);
													};

													var alternate = ($$anchor) => {
														var td_2 = root_6();
														var text_5 = child(td_2, true);

														reset(td_2);
														template_effect(($0) => set_text(text_5, $0), [() => numToString(get(summary).offloaded)]);
														append($$anchor, td_2);
													};

													add_svelte_meta(
														() => if_block(node_3, ($$render) => {
															if (get(summary).offloaded_url) $$render(consequent); else $$render(alternate, -1);
														}),
														'if',
														OffloadStatusFlyout,
														171,
														6
													);
												}

												var node_4 = sibling(node_3);

												{
													var consequent_1 = ($$anchor) => {
														var td_3 = root_7();
														var a_1 = child(td_3);
														var text_6 = child(a_1, true);

														reset(a_1);
														reset(td_3);

														template_effect(
															($0) => {
																set_attribute(a_1, 'href', get(summary).not_offloaded_url);
																set_text(text_6, $0);
															},
															[() => numToString(get(summary).not_offloaded)]
														);

														append($$anchor, td_3);
													};

													var alternate_1 = ($$anchor) => {
														var td_4 = root_8();
														var text_7 = child(td_4, true);

														reset(td_4);
														template_effect(($0) => set_text(text_7, $0), [() => numToString(get(summary).not_offloaded)]);
														append($$anchor, td_4);
													};

													add_svelte_meta(
														() => if_block(node_4, ($$render) => {
															if (get(summary).not_offloaded_url) $$render(consequent_1); else $$render(alternate_1, -1);
														}),
														'if',
														OffloadStatusFlyout,
														178,
														6
													);
												}

												reset(tr_1);
												template_effect(() => set_text(text_3, get(summary).name));
												append($$anchor, tr_1);
											}),
											'each',
											OffloadStatusFlyout,
											168,
											4
										);

										reset(tbody);

										var node_5 = sibling(tbody);

										{
											var consequent_2 = ($$anchor) => {
												var tfoot = root_9();
												var tr_2 = child(tfoot);
												var td_5 = child(tr_2);
												var text_8 = child(td_5, true);

												reset(td_5);

												var td_6 = sibling(td_5);
												var text_9 = child(td_6, true);

												reset(td_6);

												var td_7 = sibling(td_6);
												var text_10 = child(td_7, true);

												reset(td_7);
												reset(tr_2);
												reset(tfoot);

												template_effect(
													($0, $1) => {
														set_text(text_8, $strings().summary_total_row_title);
														set_text(text_9, $0);
														set_text(text_10, $1);
													},
													[
														() => numToString($counts().offloaded),
														() => numToString($counts().not_offloaded)
													]
												);

												append($$anchor, tfoot);
											};

											add_svelte_meta(
												() => if_block(node_5, ($$render) => {
													if ($summaryCounts().length > 1) $$render(consequent_2);
												}),
												'if',
												OffloadStatusFlyout,
												189,
												4
											);
										}

										reset(table);

										template_effect(() => {
											set_text(text, $strings().summary_type_title);
											set_text(text_1, $strings().summary_offloaded_title);
											set_text(text_2, $strings().summary_not_offloaded_title);
										});

										append($$anchor, table);
									}),
									$$slots: { default: true }
								}),
								'component',
								OffloadStatusFlyout,
								157,
								2,
								{ componentTag: 'PanelRow' }
							);

							var node_6 = sibling(node_2, 2);

							{
								var consequent_3 = ($$anchor) => {
									var fragment_3 = comment();
									var node_7 = first_child(fragment_3);

									add_svelte_meta(() => snippet(node_7, () => $$props.footer), 'render', OffloadStatusFlyout, 202, 3);
									append($$anchor, fragment_3);
								};

								var alternate_2 = ($$anchor) => {
									add_svelte_meta(
										() => PanelRow($$anchor, {
											footer: true,
											class: 'upsell',
											children: wrap_snippet(OffloadStatusFlyout, ($$anchor, $$slotProps) => {
												var fragment_5 = root_12();
												var node_8 = first_child(fragment_5);

												{
													var consequent_4 = ($$anchor) => {
														var p = root_13();

														html(p, $offloadRemainingUpsell, true);
														reset(p);
														append($$anchor, p);
													};

													add_svelte_meta(
														() => if_block(node_8, ($$render) => {
															if ($offloadRemainingUpsell()) $$render(consequent_4);
														}),
														'if',
														OffloadStatusFlyout,
														205,
														4
													);
												}

												var a_2 = sibling(node_8, 2);
												var img = child(a_2);
												var text_11 = sibling(img);

												reset(a_2);

												template_effect(() => {
													set_attribute(a_2, 'href', $urls().upsell_discount);
													set_attribute(img, 'src', $urls().assets + "img/icon/stars.svg");
													set_text(text_11, ` ${$strings().offload_remaining_upsell_cta ?? ''}`);
												});

												append($$anchor, fragment_5);
											}),
											$$slots: { default: true }
										}),
										'component',
										OffloadStatusFlyout,
										204,
										3,
										{ componentTag: 'PanelRow' }
									);
								};

								add_svelte_meta(
									() => if_block(node_6, ($$render) => {
										if ($$props.footer) $$render(consequent_3); else $$render(alternate_2, -1);
									}),
									'if',
									OffloadStatusFlyout,
									201,
									2
								);
							}

							append($$anchor, fragment_2);
						}),
						$$slots: { default: true }
					}),
					'component',
					OffloadStatusFlyout,
					141,
					1,
					{ componentTag: 'Panel' }
				);
			}
		};

		add_svelte_meta(
			() => if_block(node_1, ($$render) => {
				if (expanded()) $$render(consequent_5);
			}),
			'if',
			OffloadStatusFlyout,
			140,
			0
		);
	}

	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

OffloadStatus[FILENAME] = 'ui/components/OffloadStatus.svelte';

var root_1 = add_locations(from_html(`<img class="icon type"/>`), OffloadStatus[FILENAME], [[103, 3]]);
var root$3 = add_locations(from_html(`<div><div class="nav-status"><!> <p class="status-text"><strong> </strong> <span></span></p> <!></div> <!></div>`), OffloadStatus[FILENAME], [[92, 0, [[95, 1, [[110, 2, [[114, 3], [115, 3]]]]]]]]);

function OffloadStatus($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, OffloadStatus);

	var $$ownership_validator = create_ownership_validator($$props);

	const $counts = () => (
		validate_store(counts, 'counts'),
		store_get(counts, '$counts', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {boolean} [expanded] - Controls whether flyout is visible or not.
	 * @property {any} [flyoutButton]
	 * @property {boolean} [hasFocus]
	 * @property {import("svelte").Snippet} [flyout]
	 */
	/** @type {Props} */
	let expanded = prop($$props, 'expanded', 15, false),
		flyoutButton = prop($$props, 'flyoutButton', 31, () => tag_proxy(proxy({}), 'flyoutButton')),
		hasFocus = prop($$props, 'hasFocus', 15, false);

	/**
	 * Returns the numeric percentage progress for total offloaded media.
	 *
	 * @param {number} total
	 * @param {number} offloaded
	 *
	 * @return {number}
	 */
	function getPercentComplete(total, offloaded) {
		if (total < 1 || offloaded < 1) {
			return 0;
		}

		const percent = Math.floor(Math.abs(offloaded / total * 100));

		if (percent > 100) {
			return 100;
		}

		return percent;
	}

	let percentComplete = tag(user_derived(() => getPercentComplete($counts().total, $counts().offloaded)), 'percentComplete');
	let complete = tag(user_derived(() => get(percentComplete) >= 100), 'complete');

	/**
	 * Returns a formatted title string reflecting the current status.
	 *
	 * @param {number} percent
	 * @param {number} total
	 * @param {number} offloaded
	 * @param {string} description
	 *
	 * @return {string}
	 */
	function getTitle(percent, total, offloaded, description) {
		return percent + "% (" + numToString(offloaded) + "/" + numToString(total) + ") " + description;
	}

	let title = tag(user_derived(() => getTitle(get(percentComplete), $counts().total, $counts().offloaded, $strings().offloaded)), 'title');

	/**
	 * Handles a click to toggle the flyout.
	 */
	function handleClick() {
		expanded(!expanded());
		flyoutButton().focus();

		// We've handled the click.
		return true;
	}

	/**
	 * Keep track of when a child control gets mouse focus.
	 */
	function handleMouseEnter() {
		hasFocus(true);
	}

	/**
	 * Keep track of when a child control loses mouse focus.
	 */
	function handleMouseLeave() {
		hasFocus(false);
	}

	var $$exports = { ...legacy_api() };
	var div = root$3();
	let classes;
	var div_1 = child(div);
	var node = child(div_1);

	{
		var consequent = ($$anchor) => {
			var img = root_1();

			template_effect(() => {
				set_attribute(img, 'src', $urls().assets + "img/icon/licence-checked.svg");
				set_attribute(img, 'alt', get(title));
				set_attribute(img, 'title', get(title));
			});

			append($$anchor, img);
		};

		add_svelte_meta(
			() => if_block(node, ($$render) => {
				if (get(complete)) $$render(consequent);
			}),
			'if',
			OffloadStatus,
			102,
			2
		);
	}

	var p = sibling(node, 2);
	var strong = child(p);
	var text = child(strong);

	var span = sibling(strong, 2);

	html(span, () => $strings().offloaded, true);

	var node_1 = sibling(p, 2);

	add_svelte_meta(
		() => ProgressBar(node_1, {
			get percentComplete() {
				return get(percentComplete);
			},

			get title() {
				return get(title);
			}
		}),
		'component',
		OffloadStatus,
		117,
		2,
		{ componentTag: 'ProgressBar' }
	);

	var node_2 = sibling(div_1, 2);

	{
		var consequent_1 = ($$anchor) => {
			var fragment = comment();
			var node_3 = first_child(fragment);

			add_svelte_meta(() => snippet(node_3, () => $$props.flyout), 'render', OffloadStatus, 123, 2);
			append($$anchor, fragment);
		};

		var alternate = ($$anchor) => {
			{
				$$ownership_validator.binding('expanded', OffloadStatusFlyout, expanded);
				$$ownership_validator.binding('hasFocus', OffloadStatusFlyout, hasFocus);
				$$ownership_validator.binding('flyoutButton', OffloadStatusFlyout, flyoutButton);

				add_svelte_meta(
					() => OffloadStatusFlyout($$anchor, {
						get expanded() {
							return expanded();
						},

						set expanded($$value) {
							expanded($$value);
						},

						get hasFocus() {
							return hasFocus();
						},

						set hasFocus($$value) {
							hasFocus($$value);
						},

						get buttonRef() {
							return flyoutButton();
						},

						set buttonRef($$value) {
							flyoutButton($$value);
						}
					}),
					'component',
					OffloadStatus,
					125,
					2,
					{ componentTag: 'OffloadStatusFlyout' }
				);
			}
		};

		add_svelte_meta(
			() => if_block(node_2, ($$render) => {
				if ($$props.flyout) $$render(consequent_1); else $$render(alternate, -1);
			}),
			'if',
			OffloadStatus,
			122,
			1
		);
	}

	template_effect(() => {
		classes = set_class(div, 1, 'nav-status-wrapper svelte-c4vmjd', null, classes, { complete: get(complete) });
		set_attribute(div_1, 'title', get(title));
		set_attribute(p, 'title', get(title));
		set_text(text, `${get(percentComplete) ?? ''}%`);
	});

	delegated('click', div_1, handleClick);
	event('mouseenter', div_1, handleMouseEnter);
	event('mouseleave', div_1, handleMouseLeave);
	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

delegate(['click']);

Nav[FILENAME] = 'ui/components/Nav.svelte';

var root$2 = add_locations(from_html(`<div class="nav"><div class="items"><ul class="nav"></ul> <!></div></div>`), Nav[FILENAME], [[14, 0, [[15, 1, [[16, 2]]]]]]);

function Nav($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Nav);

	const $pages = () => (
		validate_store(pages, 'pages'),
		store_get(pages, '$pages', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();
	var $$exports = { ...legacy_api() };

	var /**
	 * @typedef {Object} Props
	 * @property {import("svelte").Snippet} [children]
	 */
	/** @type {Props} */
	div = root$2();

	var div_1 = child(div);
	var ul = child(div_1);

	add_svelte_meta(
		() => each(ul, 5, $pages, (tab) => tab.position, ($$anchor, tab) => {
			var fragment = comment();
			var node = first_child(fragment);

			{
				var consequent = ($$anchor) => {
					add_svelte_meta(
						() => NavItem($$anchor, {
							get tab() {
								return get(tab);
							}
						}),
						'component',
						Nav,
						19,
						5,
						{ componentTag: 'NavItem' }
					);
				};

				add_svelte_meta(
					() => if_block(node, ($$render) => {
						if (get(tab).nav && get(tab).title) $$render(consequent);
					}),
					'if',
					Nav,
					18,
					4
				);
			}

			append($$anchor, fragment);
		}),
		'each',
		Nav,
		17,
		3
	);

	var node_1 = sibling(ul, 2);

	{
		var consequent_1 = ($$anchor) => {
			var fragment_2 = comment();
			var node_2 = first_child(fragment_2);

			add_svelte_meta(() => snippet(node_2, () => $$props.children), 'render', Nav, 24, 3);
			append($$anchor, fragment_2);
		};

		var alternate = ($$anchor) => {
			add_svelte_meta(() => OffloadStatus($$anchor, {}), 'component', Nav, 26, 3, { componentTag: 'OffloadStatus' });
		};

		add_svelte_meta(
			() => if_block(node_1, ($$render) => {
				if ($$props.children) $$render(consequent_1); else $$render(alternate, -1);
			}),
			'if',
			Nav,
			23,
			2
		);
	}
	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

Pages[FILENAME] = 'ui/components/Pages.svelte';

var root$1 = add_locations(from_html(`<!> <div><!> <!></div>`, 1), Pages[FILENAME], [[21, 0]]);

function Pages($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Pages);

	const $routes = () => (
		validate_store(routes, 'routes'),
		store_get(routes, '$routes', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();

	/**
	 * @typedef {Object} Props
	 * @property {any} [nav] - These components can be overridden.
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 */
	/** @type {Props} */
	let nav = prop($$props, 'nav', 3, Nav),
		classes = prop($$props, 'class', 3, "");

	const NavComponent = tag(user_derived(nav), 'NavComponent');
	var $$exports = { ...legacy_api() };
	var fragment = root$1();
	var node = first_child(fragment);

	add_svelte_meta(
		() => component(node, () => get(NavComponent), ($$anchor, NavComponent_1) => {
			NavComponent_1($$anchor, {});
		}),
		'component',
		Pages,
		19,
		0,
		{ componentTag: 'NavComponent' }
	);

	var div = sibling(node, 2);
	var node_1 = child(div);

	add_svelte_meta(
		() => Router(node_1, {
			get routes() {
				return $routes();
			}
		}),
		'component',
		Pages,
		22,
		1,
		{ componentTag: 'Router' }
	);

	var node_2 = sibling(node_1, 2);

	{
		var consequent = ($$anchor) => {
			var fragment_1 = comment();
			var node_3 = first_child(fragment_1);

			add_svelte_meta(() => snippet(node_3, () => $$props.children), 'render', Pages, 24, 2);
			append($$anchor, fragment_1);
		};

		var alternate = ($$anchor) => {};

		add_svelte_meta(
			() => if_block(node_2, ($$render) => {
				if ($$props.children) $$render(consequent); else $$render(alternate, -1);
			}),
			'if',
			Pages,
			23,
			1
		);
	}
	template_effect(() => set_class(div, 1, `wpome-wrapper ${classes() ?? ''}`));
	append($$anchor, fragment);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

Sidebar[FILENAME] = 'ui/lite/Sidebar.svelte';

var root = add_locations(from_html(`<div class="as3cf-sidebar lite"><a class="as3cf-banner"></a> <div class="as3cf-upgrade-details"><h1>Upgrade</h1> <h2>Gain access to more features when you upgrade to WP Offload Media</h2> <ul><li>Email support</li> <li>Offload existing media items</li> <li>Manage offloaded files in WordPress</li> <li>Serve assets like JS, CSS, and fonts from CloudFront or another CDN</li> <li>Deliver private media via CloudFront</li> <li>Offload media from popular plugins like WooCommerce, Easy Digital Downloads, BuddyBoss, and more</li></ul></div> <div class="subscribe"><h2>Get up to 40% off your first year of WP Offload Media!</h2> <a class="button btn-lg btn-primary">Get the discount</a> <p class="discount-applied">* Discount applied automatically.</p></div></div>`), Sidebar[FILENAME], [
	[
		5,
		0,
		[
			[6, 1],
			[
				8,
				1,
				[
					[9, 2],
					[10, 2],
					[
						11,
						2,
						[[12, 3], [13, 3], [14, 3], [15, 3], [16, 3], [17, 3]]
					]
				]
			],
			[20, 1, [[21, 2], [22, 2], [23, 2]]]
		]
	]
]);

function Sidebar($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, false, Sidebar);

	const $urls = () => (
		validate_store(urls, 'urls'),
		store_get(urls, '$urls', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();
	var $$exports = { ...legacy_api() };

	init();

	var div = root();
	var a = child(div);
	var div_1 = sibling(a, 4);
	var a_1 = sibling(child(div_1), 2);

	template_effect(() => {
		set_attribute(a, 'href', $urls().sidebar_plugin);
		set_attribute(a, 'title', $strings().sidebar_header_title);
		set_attribute(a_1, 'href', $urls().sidebar_discount);
	});

	append($$anchor, div);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

Settings_1[FILENAME] = 'ui/lite/Settings.svelte';

function Settings_1($$anchor, $$props) {
	check_target(new.target);
	push$1($$props, true, Settings_1);

	const $settings_changed = () => (
		validate_store(settings_changed, 'settings_changed'),
		store_get(settings_changed, '$settings_changed', $$stores)
	);

	const $needs_refresh = () => (
		validate_store(needs_refresh, 'needs_refresh'),
		store_get(needs_refresh, '$needs_refresh', $$stores)
	);

	const $strings = () => (
		validate_store(strings, 'strings'),
		store_get(strings, '$strings', $$stores)
	);

	const $needs_access_keys = () => (
		validate_store(needs_access_keys, 'needs_access_keys'),
		store_get(needs_access_keys, '$needs_access_keys', $$stores)
	);

	const $settings = () => (
		validate_store(settings, 'settings'),
		store_get(settings, '$settings', $$stores)
	);

	const $defaultStorageProvider = () => (
		validate_store(defaultStorageProvider, 'defaultStorageProvider'),
		store_get(defaultStorageProvider, '$defaultStorageProvider', $$stores)
	);

	const $config = () => (
		validate_store(config, 'config'),
		store_get(config, '$config', $$stores)
	);

	const $current_settings = () => (
		validate_store(current_settings, 'current_settings'),
		store_get(current_settings, '$current_settings', $$stores)
	);

	const [$$stores, $$cleanup] = setup_stores();
	let init = prop($$props, 'init', 19, () => ({}));

	// During initialization set config store to passed in values to avoid undefined values in components during mount.
	// This saves having to do a lot of checking of values before use.
	// svelte-ignore state_referenced_locally
	config.set(init());

	pages.set(defaultPages);

	// Add Lite specific pages.
	addPages();

	setContext("sidebar", Sidebar);

	/**
	 * Handles state update event's changes to config.
	 *
	 * @param {Object} config
	 *
	 * @return {Promise<void>}
	 */
	async function handleStateUpdate(config) {
		if (config.upgrades.is_upgrading) {
			store_set(settingsLocked, true);

			const notification = {
				id: "as3cf-media-settings-locked",
				type: "warning",
				dismissible: false,
				only_show_on_tab: "media",
				heading: config.upgrades.locked_notifications[config.upgrades.running_upgrade],
				icon: "notification-locked.svg",
				plainHeading: true
			};

			notifications.add(notification);

			if ($settings_changed()) {
				settings.reset();
			}
		} else if ($needs_refresh()) {
			store_set(settingsLocked, true);

			const notification = {
				id: "as3cf-media-settings-locked",
				type: "warning",
				dismissible: false,
				only_show_on_tab: "media",
				heading: $strings().needs_refresh,
				icon: "notification-locked.svg",
				plainHeading: true
			};

			notifications.add(notification);
		} else {
			store_set(settingsLocked, false);
			notifications.delete("as3cf-media-settings-locked");
		}

		// Show a persistent error notice if bucket can't be accessed.
		if ($needs_access_keys() && (strict_equals($settings().provider, $defaultStorageProvider(), false) || strict_equals($settings().bucket.length, 0, false))) {
			const notification = {
				id: "as3cf-needs-access-keys",
				type: "error",
				dismissible: false,
				only_show_on_tab: "media",
				hide_on_parent: true,
				heading: $strings().needs_access_keys,
				plainHeading: true
			};

			notifications.add(notification);
		} else {
			notifications.delete("as3cf-needs-access-keys");
		}
	}

	// Catch changes to needing access credentials as soon as possible.
	user_pre_effect(() => {
		if ($needs_access_keys()) {
			handleStateUpdate($config());
		}
	});

	onMount(() => {
		// Make sure state dependent data is up-to-date.
		handleStateUpdate($config());

		// When state info is fetched we need some extra processing of the data.
		postStateUpdateCallbacks.update((_callables) => {
			return [..._callables, handleStateUpdate];
		});
	});

	// Make sure all inline notifications are in place.
	user_pre_effect(() => {
		settings_notifications.update((notices) => settingsNotifications.process(notices, $settings(), $current_settings(), $strings()));
	});

	var $$exports = { ...legacy_api() };

	add_svelte_meta(
		() => Settings($$anchor, {
			get header() {
				return Header_1;
			},

			children: wrap_snippet(Settings_1, ($$anchor, $$slotProps) => {
				add_svelte_meta(() => Pages($$anchor, { class: 'lite-wrapper' }), 'component', Settings_1, 124, 1, { componentTag: 'Pages' });
			}),
			$$slots: { default: true }
		}),
		'component',
		Settings_1,
		123,
		0,
		{ componentTag: 'Settings' }
	);

	var $$pop = pop($$exports);

	$$cleanup();

	return $$pop;
}

mount(
	Settings_1,
	{
		target: document.getElementById( "as3cf-settings" ),
		props: {
			init: as3cf_settings
		}
	}
);
//# sourceMappingURL=settings.js.map
