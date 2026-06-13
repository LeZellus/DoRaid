import { startStimulusApp } from '@symfony/stimulus-bundle';
import SelectController from './controllers/select_controller.js';

const app = startStimulusApp();
app.register('select', SelectController);
