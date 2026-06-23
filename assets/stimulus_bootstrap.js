import { startStimulusApp } from '@symfony/stimulus-bundle';
import SelectController from './controllers/select_controller.js';
import RevealController from './controllers/reveal_controller.js';
import CountdownController from './controllers/countdown_controller.js';
import NotificationPollController from './controllers/notification_poll_controller.js';
import ScrollBottomController from './controllers/scroll_bottom_controller.js';

const app = startStimulusApp();
app.register('select', SelectController);
app.register('reveal', RevealController);
app.register('countdown', CountdownController);
app.register('notification-poll', NotificationPollController);
app.register('scroll-bottom', ScrollBottomController);
