import { startStimulusApp } from '@symfony/stimulus-bundle';
import SelectController from './controllers/select_controller.js';
import RevealController from './controllers/reveal_controller.js';

const app = startStimulusApp();
app.register('select', SelectController);
app.register('reveal', RevealController);
