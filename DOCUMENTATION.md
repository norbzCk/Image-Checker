# This is a project documentation for the ImageCheck, 
it is a project that when i upload an image to it, it can detect whether it is ai-generated or taken by a camera.

## when i was tackling this challenge of building an ImageCheck, i went through the following:
First i wanted to understand what would make this project actually work, what thing would make it able to detect whether an image is ai-genarated or taken by camera

According to the rules provided i was required to use a lamp stack for this project, i also heard of some thing called SynthId,but was not sure what was it and how it works
Provided a link , i went through it and some one from google was explaining about it , so i got some grasp of it
I later went to do more research on it,

### Here is what i found:
SynthId is an invisible watermarking and identification technology developed by GoogleDeepMind to track and detect Ai genarated content

Further more my research suggested that SynthId alone would not be a suitable choice for me to use as an ImageCheck detector And the reason is that SynthId only answers "is a SynthId watermark present or not", but it is not a general AI-generated content detector
SO i also had to use other ways ,including the C2PA (Coalition for content Provenance and Authenticity)
It is an open technical standard that maps out the origin and editing history of digital media.Often described as a "nutrition label" for online content, it creates a secure, verifiable trail known as Content Credentials

### And here is how C2PA Works:
Instead of trying to scan a file and guess if it is fake, C2PA relies on opt-in transparency from creation to publication

1. Capture: A C2PA-compatible camera (such as select models from Leica or Sony) logs the exact time, date, and location when a photo or video is taken.
2. Edit: If the file is modified in editing software like Adobe Photoshop, the software logs exactly what edits were made (e.g., "cropped," "color adjusted," or "generative AI fill used").
3. The Manifest: All of this history is bundled into a tiny piece of metadata called a manifest.
4. Cryptographic Signing: The manifest is secured using digital signatures (similar to the technology that secures HTTPS websites). If anyone tries to modify the picture or alter the history maliciously, the cryptographic seal breaks, flagging the file as tampered with

it basically checks for the metadata of the Content

And SynthId only checks for the watermarking

While C2PA is a powerful tool for media transparency, it faces several technical and social hurdles. The primary limitation of C2PA is that its security metadata can be easily stripped away, breaking the chain of verification



## THE FLOW OF THE IMAGECHECKER
### ->Step 1:
you make an upload , and it goes->(public/index.php, src/UploadValidator.php).
The page checks the CSRF token (proof the form came from this site). Then it checks the file itself:

Real file type: it inspects the file’s contents, not its name.
Valid image: it must be a JPEG, PNG, WebP or GIF, under 10 MB and under 40 million pixels.

Anything else is rejected here, before any analysis happens.

### Step 2: it’s stored (src/AnalysisService.php).
The file is saved under a random name in /var/www/uploads, outside the website folder. A row is added to MySQL with status PENDING

### Step 3: PHP asks the detector here, (src/InferenceClient.php).
PHP sends the file over the internal Docker network to inference:8000/analyze, with a secret token so only the website can use it.


### Step 4: the detector examines it (inference/app/main.py). In this order:
1:Open and validate the image again. The detector doesn’t trust PHP’s checks.
2:EXIF (Exchangeable Image File Format), hints (origin.py): are camera make or model tags present? This is read first, because converting the image later drops them.
3:C2PA check (origin.py): it reads the Content Credentials from the original, unmodified bytes. It looks for a signed claim that the image was made by a generative system.
4:The classifier (classifier.py): the image is converted to RGB and passed to the pretrained model. The model scores “artificial” and “human”, and the code adds up the labels listed in AI_LABELS to get one number from 0 to 1.

### Step 5: is the actual classification

After the detector has gathered its evidence, the classification is based on four simple rules, checked in order, and it stops at the first one that fits.

Does the file have a label saying “made by AI”? If yes, the answer is AI-generated. A label is direct proof, so nothing else matters.
If not, is the model’s AI score 80% or higher? If yes, the answer is Likely AI.
If not, is the score 20% or lower? If yes, the answer is Likely real.
If none of those fit, the score is between 20% and 80%. The answer is Uncertain, because the model isn’t sure either way.

#### Here are a few examples:

Situation	Result
Image has an AI label (score doesn’t matter)	AI-generated
No label, model says 92%	Likely AI
No label, model says 6%	Likely real
No label, model says anyhting in between 	Uncertain

### Step 6: saved and shown (src/AnalysisRepository.php, templates/home.php).
Using PHP the result is stored, probability, model name and signals into MySQL and sets the status to DONE. It then redirects to /?id=N, which shows the result, the percentage meter and the C2PA and EXIF lines. If the detector was unreachable, the status becomes FAILED and a retry button appears.



## How Hugging face is involved:
Hugging Face is the free website where the imagecheck gets its AI model. The detector container uses a Python library called transformers, and one line in classifier.py tells it the name of the model, umm-maybe/AI-image-detector. The first time the container starts, the library goes to Hugging Face, downloads that model’s files once, and saves them in a local folder (a Docker volume). From then on, the model loads from your own machine. Every image you upload is analysed locally by that downloaded model, and your pictures are never sent to Hugging Face. The model’s name is stored in one setting (MODEL_ID in .env), so you can switch to a different detector without touching any code.

## The challenges that i faced:
I realized i did not know about docker that much , there are things that were new to me , i went to understand what are sockets , how docker works, and how to run docker commands , i also did not know much about docker hub

Another challenge is that the stack , there was a time i diverged i used a Lamp stack + Python FastApi, then later i came to realize i was not following a lamp stack as required, so i made a shift to pure lamp stack in one container, though there are some parts that involve another language, ie python , in the making the logic of calling an ai model because  with php it would be impossble as i researched,so there are files written in pythin , but they dont make the stack used not to be a lamp stack

And generally i had to search for a lot of things cause they were new to me


## Tech Stack used:The LAMP-Stack

Linux: every Docker container runs Linux.
Apache and PHP: they run the website, the upload checks and the result page.
MySQL: it stores every upload and its verdict

## Limitations
It only knows the AI tools it has been trained on. A new tool can trick it.
Screenshots and resized pictures make it less accurate.
A real photo that was edited a lot can be called AI by mistake.
“87%” means the model feels sure. It doesn’t mean it is right 87 times out of 100.
The 80% and 20% limits are just my guesses. They were never tested.

Problems with the label check

Most pictures have no label, and no label doesn’t mean the picture is real.
Someone can delete the label from an AI picture, or add a fake one to a real photo.
The code doesn’t check if the label is genuine, so a fake “AI” label would still win. This is the first thing I would fix.
A real photo with one small AI edit would be called fully AI.
