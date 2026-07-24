// Week 3 Lab Task - SkillProof Interactive Dashboard
// DOM container reference is cached for better performance.
const appContainer = document.getElementById('app');

// Base application layout rendered dynamically
appContainer.innerHTML = `
  <header class="mb-8 text-center">
    <h1 class="text-3xl md:text-4xl font-extrabold text-blue-900">
      SkillProof Developer Registration
    </h1>
    <p class="text-gray-600 mt-2">
      Real-time validation, password strength checking, and reusable dashboard components.
    </p>
  </header>

  <main class="bg-white p-6 md:p-8 rounded-xl shadow-md border border-gray-100 max-w-lg mx-auto">
    <form id="registration-form" novalidate class="space-y-6">

      <div>
        <label class="block text-sm font-bold text-gray-700 mb-1" for="user-email">
          Email Address
        </label>
        <input
          type="email"
          id="user-email"
          required
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none state-transition"
          placeholder="e.g., student@university.edu"
        >
        <p id="email-error" class="text-red-500 text-xs mt-1 hidden">
          Please provide a valid email address.
        </p>
      </div>

      <div>
        <label class="block text-sm font-bold text-gray-700 mb-1" for="user-password">
          Secure Password
        </label>
        <input
          type="password"
          id="user-password"
          required
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none state-transition"
          placeholder="Min 8 characters, uppercase, number, special character"
        >

        <div class="h-2 w-full bg-gray-200 rounded-full mt-2 overflow-hidden">
          <div id="strength-meter" class="h-full w-0 bg-red-500 state-transition"></div>
        </div>

        <p id="strength-text" class="text-xs text-gray-500 mt-1">
          Strength: Empty
        </p>
      </div>

      <button
        type="submit"
        class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 px-4 rounded-lg shadow state-transition"
      >
        Create Secure Account
      </button>
    </form>
  </main>
`;

// DOM reference selectors
const registrationForm = document.getElementById('registration-form');
const userEmail = document.getElementById('user-email');
const emailError = document.getElementById('email-error');
const userPassword = document.getElementById('user-password');
const strengthMeter = document.getElementById('strength-meter');
const strengthText = document.getElementById('strength-text');

// Rakib base submit handler
registrationForm.addEventListener('submit', function (event) {
  event.preventDefault();

  const isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(userEmail.value);
  const isPasswordValid = userPassword.value.length >= 8;

  if (isEmailValid && isPasswordValid) {
    const successBox = document.createElement('section');
    successBox.className =
      'mt-8 bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-xl text-center max-w-lg mx-auto shadow-md';

    const title = document.createElement('h2');
    title.className = 'text-2xl font-bold mb-2';
    title.textContent = 'Account Created Safely!';

    const message = document.createElement('p');
    message.className = 'text-sm';
    message.textContent =
      'Your SkillProof registration was validated successfully.';

    successBox.appendChild(title);
    successBox.appendChild(message);
    appContainer.appendChild(successBox);
  } else {
    alert('Validation failed! Please correct the inputs before submitting.');
  }
});