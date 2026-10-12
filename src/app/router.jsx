import { Routes, Route, Navigate } from 'react-router-dom';
import RequireAuth from './RequireAuth';
import AppLayout from '../shared/layout/AppLayout';
import LoginPage from '../features/auth/LoginPage';
import SetupPage from '../features/setup/SetupPage';
import HomePage from '../features/home/HomePage';
import AccountManage from '../features/accounts/AccountManage';
import RolesManage from '../features/roles/RolesManage';
import SectionsPage from '../features/refs/SectionsPage';
import NiveauxPage from '../features/refs/NiveauxPage';
import SeriesPage from '../features/refs/SeriesPage';
import OptionsPage from '../features/refs/OptionsPage';
import MoisJoursPage from '../features/refs/MoisJoursPage';
import ElevesPage from '../features/students/ElevesPage';
import EleveFichePage from '../features/students/EleveFichePage';
import ClassesPage from '../features/classes/ClassesPage';
import MatieresPage from '../features/matieres/MatieresPage';
import EvaluationsPage from '../features/evaluations/EvaluationsPage';
import EnseignantsPage from '../features/teachers/EnseignantsPage';
import EnseignantFichePage from '../features/teachers/EnseignantFichePage';
import HabilitationsPage from '../features/habilitations/HabilitationsPage';
import NotesPage from '../features/notes/NotesPage';
import NotesConsultationPage from '../features/notes/NotesConsultationPage';
import ParametresNiveauPage from '../features/notes/ParametresNiveauPage';
import CompetencesApcPage from '../features/notes/CompetencesApcPage';
import ConseilClassePage from '../features/notes/ConseilClassePage';
import BulletinsPage from '../features/bulletins/BulletinsPage';
import BulletinFichePage from '../features/bulletins/BulletinFichePage';
import ScolaritePage from '../features/scolarite/ScolaritePage';
import ScolariteFichePage from '../features/scolarite/ScolariteFichePage';
import InscriptionsPage from '../features/scolarite/InscriptionsPage';
import CategoriesPage from '../features/scolarite/CategoriesPage';
import ReductionsPage from '../features/scolarite/ReductionsPage';
import AttributionReductionsPage from '../features/scolarite/AttributionReductionsPage';
import AbsencesPage from '../features/absences/AbsencesPage';
import AbsenceFichePage from '../features/absences/AbsenceFichePage';
import AbsencesLogsPage from '../features/absences/AbsencesLogsPage';
import ParametresAbsencePage from '../features/absences/ParametresAbsencePage';
import DecisionsPassagePage from '../features/notes/DecisionsPassagePage';

export default function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/setup" element={<SetupPage />} />
      <Route element={<RequireAuth />}>
        <Route element={<AppLayout />}>
          <Route index element={<HomePage />} />
          <Route path="accountManage" element={<RequireAuth requirePermission="accounts.manage"><AccountManage /></RequireAuth>} />
          <Route path="rolesManage" element={<RequireAuth requirePermission="roles.manage"><RolesManage /></RequireAuth>} />
          <Route path="refs/sections" element={<SectionsPage />} />
          <Route path="refs/niveaux" element={<NiveauxPage />} />
          <Route path="refs/series" element={<SeriesPage />} />
          <Route path="refs/options" element={<OptionsPage />} />
          <Route path="refs/mois-jours" element={<MoisJoursPage />} />
          <Route path="eleves" element={<RequireAuth requirePermission="students.view"><ElevesPage /></RequireAuth>} />
          <Route path="eleves/:matricule" element={<RequireAuth requirePermission="students.view"><EleveFichePage /></RequireAuth>} />
          <Route path="classes" element={<RequireAuth requirePermission="settings.view"><ClassesPage /></RequireAuth>} />
          <Route path="parametres/matieres" element={<RequireAuth requirePermission="settings.view"><MatieresPage /></RequireAuth>} />
          <Route path="parametres/evaluations" element={<RequireAuth requirePermission="settings.view"><EvaluationsPage /></RequireAuth>} />
          <Route path="enseignants" element={<RequireAuth requirePermission="hr.view"><EnseignantsPage /></RequireAuth>} />
          <Route path="enseignants/:matricule" element={<RequireAuth requirePermission="hr.view"><EnseignantFichePage /></RequireAuth>} />
          <Route path="habilitations" element={<RequireAuth requirePermission="hr.view"><HabilitationsPage /></RequireAuth>} />
          <Route path="notes" element={<RequireAuth requirePermission="grades.view"><NotesPage /></RequireAuth>} />
          <Route path="notes/consultation" element={<RequireAuth requirePermission="grades.view"><NotesConsultationPage /></RequireAuth>} />
          <Route path="bulletins" element={<RequireAuth requirePermission="grades.view"><BulletinsPage /></RequireAuth>} />
          <Route path="bulletins/:matricule" element={<RequireAuth requirePermission="grades.view"><BulletinFichePage /></RequireAuth>} />
          <Route path="parametres/parametres-niveau" element={<RequireAuth requirePermission="settings.view"><ParametresNiveauPage /></RequireAuth>} />
          <Route path="parametres/competences-apc" element={<RequireAuth requirePermission="settings.view"><CompetencesApcPage /></RequireAuth>} />
          <Route path="conseil-de-classe" element={<RequireAuth requirePermission="grades.edit"><ConseilClassePage /></RequireAuth>} />
          <Route path="decisions/passage" element={<RequireAuth requirePermission="grades.view"><DecisionsPassagePage /></RequireAuth>} />
          <Route path="scolarite" element={<RequireAuth requirePermission="finance.view"><ScolaritePage /></RequireAuth>} />
          <Route path="scolarite/:matricule" element={<RequireAuth requirePermission="finance.view"><ScolariteFichePage /></RequireAuth>} />
          <Route path="inscriptions" element={<RequireAuth requirePermission="finance.view"><InscriptionsPage /></RequireAuth>} />
          <Route path="parametres/categories" element={<RequireAuth requirePermission="settings.view"><CategoriesPage /></RequireAuth>} />
          <Route path="parametres/reductions" element={<RequireAuth requirePermission="settings.view"><ReductionsPage /></RequireAuth>} />
          <Route path="reductions" element={<RequireAuth requirePermission="finance.edit"><AttributionReductionsPage /></RequireAuth>} />
          <Route path="absences" element={<RequireAuth requirePermission="students.view"><AbsencesPage /></RequireAuth>} />
          <Route path="absences/:matricule" element={<RequireAuth requirePermission="students.view"><AbsenceFichePage /></RequireAuth>} />
          <Route path="absences/logs" element={<RequireAuth requirePermission="students.view"><AbsencesLogsPage /></RequireAuth>} />
          <Route path="parametres/absence" element={<RequireAuth requirePermission="settings.view"><ParametresAbsencePage /></RequireAuth>} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}