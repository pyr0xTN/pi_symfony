import { startStimulusApp } from '@symfony/stimulus-bundle';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
