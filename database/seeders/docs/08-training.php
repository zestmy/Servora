<?php

return [
    'slug'    => 'training',
    'title'   => 'Training',
    'summary' => 'Courses, quizzes, learning paths, live sessions, certificates and the staff PIN portal.',
    'icon'    => 'academic',
    'sort'    => 80,
    'articles' => [

        [
            'slug'     => 'courses-and-quizzes',
            'title'    => 'Courses and quizzes',
            'excerpt'  => 'Write the material once, test that it landed, and keep the record.',
            'keywords' => 'training, course, quiz, LMS, learning, test, onboarding',
            'body' => <<<'HTML'
<p>Training in Servora is built around the fact that F&amp;B teams turn over and shifts are short. Material is short, tested, and recorded — so "we trained them" is a fact rather than a belief.</p>

<figure><img src="/images/docs/training.svg" alt="The training module showing assigned, completed and certificate counts, a learning path for a new kitchen hire with four steps in various states, a PIN entry panel, and a report card table"><figcaption>A learning path, the PIN portal staff use, and the report card that proves it happened.</figcaption></figure>

<h2>Courses</h2>

<p>A course is the material: text, images, video, or a link to an SOP exported from a recipe. Keep them under about fifteen minutes — a course that needs a quiet half hour will be done by nobody on a Saturday.</p>

<p>Courses can be scoped to a section or an outlet, so the grill team is not assigned front-of-house material.</p>

<h2>Quizzes</h2>

<p>A quiz is how you find out whether the course worked. Multiple choice, with a pass mark you set. Questions can carry their own translations, so a mixed-language team takes the same quiz in the language they read.</p>

<p>Attempts are recorded — every one, not just the pass. Three attempts to reach 80% is a different result from first time, and both are worth knowing.</p>

<h2>Learning paths</h2>

<p>A path is an ordered set of courses and quizzes: <em>New kitchen hire</em>, <em>Barista level 1</em>, <em>Shift leader</em>. Steps unlock in sequence, so somebody cannot take the knife-skills assessment before the food-safety course.</p>

<p>Assign a path to a person or a whole section and their progress is tracked against it.</p>

<h2>Assignments and deadlines</h2>

<p>An assignment ties a person to a course with a due date. Overdue assignments surface for their manager. Assign at hiring, not at inspection time.</p>
HTML,
        ],

        [
            'slug'     => 'live-sessions',
            'title'    => 'Live sessions',
            'excerpt'  => 'Put the same quiz on every phone in the room at once, with a leaderboard.',
            'keywords' => 'live session, quiz game, briefing, team quiz, leaderboard, host',
            'body' => <<<'HTML'
<p>A live session runs one quiz across the room simultaneously — everyone answers on their phone, the host screen shows the results as they come in, and a leaderboard sorts itself out.</p>

<h2>Running one</h2>

<ol>
  <li><strong>Learning → Live Sessions → New session.</strong> Pick the quiz.</li>
  <li>Servora shows a join code. Staff open the training portal and enter it.</li>
  <li>Start. Each question goes out with a timer; answers come back live.</li>
  <li>At the end, the leaderboard shows the scores and every attempt is recorded against the individual.</li>
</ol>

<h2>What it is good for</h2>

<p>Pre-service briefings. A new menu item, an allergen change, a hygiene reminder — six minutes at the start of a shift, with a record that it happened and who was there. It gets attention that an emailed PDF does not.</p>

<h2>What it is not for</h2>

<p>A formal assessment somebody's certification depends on. The timer and the room make it a group exercise. For that, assign the quiz individually.</p>
HTML,
        ],

        [
            'slug'     => 'certificates-and-report-cards',
            'title'    => 'Certificates and report cards',
            'excerpt'  => 'Proof for the individual, and a picture for the manager.',
            'keywords' => 'certificate, report card, progress, verify, QR, training record, audit',
            'body' => <<<'HTML'
<h2>Certificates</h2>

<p>Completing a course or a learning path can issue a certificate: the person's name, what they completed, the date, and a signatory. It downloads as a PDF and carries a QR code.</p>

<p>The QR resolves to a public verification page — anyone can scan it and confirm the certificate is real, without a login. That is what makes it worth anything to an inspector or a next employer.</p>

<h2>Report cards</h2>

<p><strong>Learning → Report Cards</strong> is the manager's view: per employee, what was assigned, what was completed, average score, certificates held. Filter by section or outlet.</p>

<p>Read it for two things: individuals who are behind, and courses everybody scores badly on. The second is usually the course's fault, not the team's.</p>

<h2>The leaderboard</h2>

<p>Optional, and worth thinking about before you switch it on. It works well where training is a shared push and badly where it becomes a public ranking of who reads slowly. Scope it to a section if you use it.</p>
HTML,
        ],

        [
            'slug'     => 'the-staff-training-portal',
            'title'    => 'The staff training portal',
            'excerpt'  => 'A separate login so floor staff can train without a Servora account.',
            'keywords' => 'training portal, PIN, staff login, LMS user, tablet, shared device',
            'body' => <<<'HTML'
<p>Most floor staff do not need a Servora account, and giving one to everybody who works a Saturday is neither practical nor safe. The training portal is its own front door.</p>

<h2>How staff get in</h2>

<p>Each person gets a numeric PIN. They open the portal — on their own phone or a shared tablet — and enter it. They see their assigned courses, their progress, and their certificates. Nothing else in Servora is reachable from there.</p>

<h2>Setting it up</h2>

<ol>
  <li><strong>Learning → Training Portal</strong>, add the staff who need access.</li>
  <li>Servora issues a PIN per person. Give it to them; they can change it.</li>
  <li>On a shared tablet, leave the portal open at the PIN screen. It signs itself out after inactivity so the next person starts fresh.</li>
</ol>

<h2>Why not just give everyone a login</h2>

<p>Because a Servora account carries permissions, an email address, a password to forget and an account to disable when somebody leaves. A training PIN carries none of that, and it is the correct amount of access for somebody whose only task is to watch a five-minute course about allergens.</p>
HTML,
        ],
    ],
];
