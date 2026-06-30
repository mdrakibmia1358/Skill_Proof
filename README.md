# SkillProof - File & Branch Assignment

এই zip এ ৫টা HTML file এবং ১টা shared styles.css আছে।
নিচের table অনুযায়ী যার যার file নিজের branch এ push করবে।

| File          | কে কাজ করবে    | Branch নাম                  |
|---------------|----------------|------------------------------|
| home.html     | Rakib (Leader) | feature/dashboard-rakib      |
| login.html    | Nondini        | feature/profile-nondini      |
| register.html | Nondini        | feature/profile-nondini      |
| skills.html   | Sumaiya        | feature/skills-sumaiya       |
| recruiter.html| Maria          | feature/recruiter-maria      |
| styles.css    | সবাই ব্যবহার করবে (প্রথমে main এ push করে দাও) | main (shared) |

## কীভাবে কাজ করবে — Step by Step

### সবার আগে (Rakib করবে):
1. styles.css ফাইলটা প্রথমে main branch এ push করে দাও,
   যাতে সবাই branch বানানোর সময় এটা পেয়ে যায়।

```bash
cd ~/skillproof
git checkout main
git pull origin main
# styles.css কপি করে এখানে রাখো
git add styles.css
git commit -m "Add shared stylesheet"
git push origin main
```

### এরপর প্রত্যেকে নিজের branch বানাবে:

```bash
git checkout main
git pull origin main
git checkout -b feature/<তোমার-branch-নাম>
```

### নিজের HTML file(গুলো) এই folder এ কপি করে রাখবে, তারপর:

```bash
git add .
git commit -m "Add <file-name> page"
git push origin feature/<তোমার-branch-নাম>
```

### সবশেষে Rakib GitHub এ গিয়ে প্রত্যেকের Pull Request
main branch এ merge করে দেবে।

## ফাইলগুলো একে অপরের সাথে link করা আছে
প্রতিটা page এর উপরে navigation bar এ home.html, login.html,
skills.html, recruiter.html এর লিংক আছে। তাই সব file একই
folder এ থাকলে browser এ click করে এক page থেকে অন্য page এ
যাওয়া যাবে।
